<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate business entities (merchants) from user accounts; scope payments and related data by merchant_id.
     */
    public function up(): void
    {
        if (Schema::hasTable('merchants') && ! Schema::hasTable('merchants_legacy')) {
            Schema::rename('merchants', 'merchants_legacy');
        }

        // Legacy UUID merchants table is unused after user-based migration; drop to avoid SQLite index name
        // collisions (e.g. merchants_email_unique) when recreating `merchants`.
        Schema::dropIfExists('merchants_legacy');

        if (! Schema::hasTable('merchants')) {
            Schema::create('merchants', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('api_key')->nullable();
                $table->string('api_key_hash')->nullable();
                $table->string('api_key_last_four', 8)->nullable();
                $table->timestamp('api_key_generated_at')->nullable();
                $table->text('api_secret')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'merchant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            });
        }

        $this->seedMerchantsFromMerchantUsers();

        if (Schema::hasColumn('users', 'merchant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();
            });
        }

        $this->migratePaymentsToMerchantId();
        $this->migrateMerchantGatewaysToMerchantId();
        $this->migrateWalletsToMerchantId();
        $this->migrateMerchantWalletSettingsToMerchantId();
        $this->migratePlatformFeesForeignKey();

        DB::table('users')
            ->where('role', 'merchant')
            ->update(['role' => 'merchant_user']);

        $this->clearUserApiCredentialsForMerchantLinkedUsers();
    }

    public function down(): void
    {
        // Destructive rollback is not fully supported; restore would require merchants_legacy and user_id columns.
    }

    private function seedMerchantsFromMerchantUsers(): void
    {
        $rows = DB::table('users')
            ->where('role', 'merchant')
            ->orderBy('id')
            ->get();

        foreach ($rows as $u) {
            if (DB::table('merchants')->where('id', $u->id)->exists()) {
                continue;
            }

            DB::table('merchants')->insert([
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'api_key' => $u->api_key ?? null,
                'api_key_hash' => $u->api_key_hash ?? null,
                'api_key_last_four' => $u->api_key_last_four ?? null,
                'api_key_generated_at' => $u->api_key_generated_at ?? null,
                'api_secret' => $u->api_secret ?? null,
                'is_active' => (bool) $u->is_active,
                'created_at' => $u->created_at ?? now(),
                'updated_at' => $u->updated_at ?? now(),
            ]);

            DB::table('users')->where('id', $u->id)->update(['merchant_id' => $u->id]);
        }
    }

    private function migratePaymentsToMerchantId(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'user_id')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'merchant_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            });
        }

        DB::table('payments')->orderBy('id')->chunk(500, function ($payments): void {
            foreach ($payments as $p) {
                $mid = DB::table('users')->where('id', $p->user_id)->value('merchant_id');
                if ($mid !== null) {
                    DB::table('payments')->where('id', $p->id)->update(['merchant_id' => $mid]);
                }
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->index('merchant_id');
        });
    }

    private function migrateMerchantGatewaysToMerchantId(): void
    {
        if (! Schema::hasTable('merchant_gateways') || ! Schema::hasColumn('merchant_gateways', 'user_id')) {
            return;
        }

        if (! Schema::hasColumn('merchant_gateways', 'merchant_id')) {
            Schema::table('merchant_gateways', function (Blueprint $table) {
                $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            });
        }

        DB::table('merchant_gateways')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $mg) {
                $mid = DB::table('users')->where('id', $mg->user_id)->value('merchant_id');
                if ($mid !== null) {
                    DB::table('merchant_gateways')->where('id', $mg->id)->update(['merchant_id' => $mid]);
                }
            }
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'gateway_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('merchant_gateways', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->unique(['merchant_id', 'gateway_id']);
        });
    }

    private function migrateWalletsToMerchantId(): void
    {
        if (! Schema::hasTable('wallets') || ! Schema::hasColumn('wallets', 'user_id')) {
            return;
        }

        if (! Schema::hasColumn('wallets', 'merchant_id')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            });
        }

        DB::table('wallets')->orderBy('id')->chunk(500, function ($rows): void {
            foreach ($rows as $w) {
                if ($w->user_id === null) {
                    continue;
                }
                $mid = DB::table('users')->where('id', $w->user_id)->value('merchant_id');
                if ($mid !== null) {
                    DB::table('wallets')->where('id', $w->id)->update(['merchant_id' => $mid]);
                }
            }
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallet_user_type_currency_unique');
            $table->dropColumn('user_id');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();
            $table->unique(['merchant_id', 'wallet_type', 'currency'], 'wallet_merchant_type_currency_unique');
        });
    }

    private function migrateMerchantWalletSettingsToMerchantId(): void
    {
        if (! Schema::hasTable('merchant_wallet_settings') || ! Schema::hasColumn('merchant_wallet_settings', 'user_id')) {
            return;
        }

        if (! Schema::hasColumn('merchant_wallet_settings', 'merchant_id')) {
            Schema::table('merchant_wallet_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            });
        }

        DB::table('merchant_wallet_settings')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $mws) {
                $mid = DB::table('users')->where('id', $mws->user_id)->value('merchant_id');
                if ($mid !== null) {
                    DB::table('merchant_wallet_settings')->where('id', $mws->id)->update(['merchant_id' => $mid]);
                }
            }
        });

        Schema::table('merchant_wallet_settings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('merchant_wallet_settings', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
            $table->unique(['merchant_id']);
        });
    }

    private function migratePlatformFeesForeignKey(): void
    {
        if (! Schema::hasTable('platform_fees') || ! Schema::hasColumn('platform_fees', 'merchant_id')) {
            return;
        }

        Schema::table('platform_fees', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
        });

        Schema::table('platform_fees', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
        });
    }

    private function clearUserApiCredentialsForMerchantLinkedUsers(): void
    {
        DB::table('users')
            ->whereNotNull('merchant_id')
            ->update([
                'api_key' => null,
                'api_key_hash' => null,
                'api_key_last_four' => null,
                'api_key_generated_at' => null,
                'api_secret' => null,
            ]);
    }
};
