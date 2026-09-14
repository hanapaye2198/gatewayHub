<?php

namespace App\Enums;

enum PaymentDisplayStatus: string
{
    case Paid = 'paid';

    case Pending = 'pending';

    case Expired = 'expired';

    case Failed = 'failed';

    case ProvisioningFailed = 'provisioning_failed';

    case Refunded = 'refunded';

    case FailedAfterPaid = 'failed_after_paid';

    /**
     * @return list<self>
     */
    public static function filterOptions(): array
    {
        return [
            self::Pending,
            self::Paid,
            self::Expired,
            self::Failed,
            self::ProvisioningFailed,
            self::Refunded,
            self::FailedAfterPaid,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Pending => 'Pending',
            self::Expired => 'Expired',
            self::Failed => 'Failed',
            self::ProvisioningFailed => 'Provisioning Failed',
            self::Refunded => 'Refunded',
            self::FailedAfterPaid => 'Failed After Paid',
        };
    }

    public function explanation(): ?string
    {
        return match ($this) {
            self::Expired => 'Payment QR expired because the customer did not complete the payment within the allowed time. No successful payment was recorded for this transaction.',
            self::Failed => 'This payment did not complete due to a payment or processing failure.',
            default => null,
        };
    }

    public function recommendedAction(): ?string
    {
        return match ($this) {
            self::Expired => 'Create a new payment if the customer still wants to pay.',
            default => null,
        };
    }
}
