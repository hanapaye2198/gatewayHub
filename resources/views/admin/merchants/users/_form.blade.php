@php
    /** @var \App\Models\Merchant $merchant */
    /** @var \App\Models\User|null $merchantUser */
    $merchantUser = $merchantUser ?? null;
@endphp

<form
    method="POST"
    action="{{ $merchantUser ? route('admin.merchants.users.update', [$merchant, $merchantUser]) : route('admin.merchants.users.store', $merchant) }}"
    class="flex max-w-xl flex-col gap-5"
>
    @csrf
    @if ($merchantUser)
        @method('PUT')
    @endif

    <flux:field>
        <flux:label>{{ __('Merchant') }}</flux:label>
        <flux:input type="text" :value="$merchant->name" disabled />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Role') }}</flux:label>
        <flux:input type="text" value="{{ __('Merchant user') }}" disabled />
    </flux:field>

    <flux:field>
        <flux:label for="name">{{ __('Name') }}</flux:label>
        <flux:input
            id="name"
            name="name"
            type="text"
            :value="old('name', $merchantUser?->name)"
            required
            autofocus
            autocomplete="name"
        />
        <flux:error name="name" />
    </flux:field>

    <flux:field>
        <flux:label for="email">{{ __('Email') }}</flux:label>
        <flux:input
            id="email"
            name="email"
            type="email"
            :value="old('email', $merchantUser?->email)"
            required
            autocomplete="email"
        />
        <flux:error name="email" />
    </flux:field>

    <flux:field>
        <flux:label for="password">{{ __('Password') }}</flux:label>
        <flux:input
            id="password"
            name="password"
            type="password"
            autocomplete="new-password"
            :required="! $merchantUser"
            viewable
        />
        @if ($merchantUser)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Leave blank to keep the current password.') }}</p>
        @endif
        <flux:error name="password" />
    </flux:field>

    <flux:field>
        <flux:label for="password_confirmation">{{ __('Confirm password') }}</flux:label>
        <flux:input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            autocomplete="new-password"
            :required="! $merchantUser"
            viewable
        />
    </flux:field>

    @php
        $activeValue = old('is_active', $merchantUser?->is_active ?? true);
        $activeChecked = filter_var($activeValue, FILTER_VALIDATE_BOOLEAN);
    @endphp
    <input type="hidden" name="is_active" value="0">
    <flux:checkbox
        name="is_active"
        value="1"
        :label="__('Active')"
        :checked="$activeChecked"
    />
    <flux:error name="is_active" />

    @if ($merchantUser)
        <dl class="grid gap-3 text-sm">
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</dt>
                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $merchantUser->created_at?->format('M d, Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Updated') }}</dt>
                <dd class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $merchantUser->updated_at?->format('M d, Y H:i') }}</dd>
            </div>
        </dl>
    @endif

    <div class="flex items-center gap-3">
        <flux:button type="submit" variant="primary">
            {{ $merchantUser ? __('Save user') : __('Create User') }}
        </flux:button>
        <flux:button variant="ghost" :href="route('admin.merchants.users.index', $merchant)" wire:navigate>
            {{ __('Cancel') }}
        </flux:button>
    </div>
</form>
