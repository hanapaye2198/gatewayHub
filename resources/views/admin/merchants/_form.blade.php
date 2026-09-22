@php
    /** @var \App\Models\Merchant|null $merchant */
    $merchant = $merchant ?? null;
@endphp

<form
    method="POST"
    action="{{ $merchant ? route('admin.merchants.update', $merchant) : route('admin.merchants.store') }}"
    class="flex max-w-xl flex-col gap-5"
>
    @csrf
    @if ($merchant)
        @method('PUT')
    @endif

    <flux:field>
        <flux:label for="name">{{ __('Merchant name') }}</flux:label>
        <flux:input
            id="name"
            name="name"
            type="text"
            :value="old('name', $merchant?->name)"
            required
            autofocus
            autocomplete="organization"
        />
        <flux:error name="name" />
    </flux:field>

    <flux:field>
        <flux:label for="email">{{ __('Email') }}</flux:label>
        <flux:input
            id="email"
            name="email"
            type="email"
            :value="old('email', $merchant?->email)"
            required
            autocomplete="email"
        />
        <flux:error name="email" />
    </flux:field>

    <flux:field>
        <flux:label for="qr_display_name">{{ __('QR display name') }}</flux:label>
        <flux:input
            id="qr_display_name"
            name="qr_display_name"
            type="text"
            :value="old('qr_display_name', $merchant?->qr_display_name)"
            autocomplete="off"
        />
        <flux:error name="qr_display_name" />
    </flux:field>

    <flux:field>
        <flux:label for="theme_color">{{ __('Theme color') }}</flux:label>
        <flux:input
            id="theme_color"
            name="theme_color"
            type="text"
            :value="old('theme_color', $merchant?->theme_color)"
            placeholder="#1D4ED8"
            autocomplete="off"
        />
        <flux:error name="theme_color" />
    </flux:field>

    <flux:field>
        <flux:label for="webhook_url">{{ __('Webhook URL') }}</flux:label>
        <flux:input
            id="webhook_url"
            name="webhook_url"
            type="url"
            :value="old('webhook_url', $merchant?->webhook_url)"
            placeholder="https://example.com/webhooks/gatewayhub"
            autocomplete="off"
        />
        <flux:error name="webhook_url" />
    </flux:field>

    <div class="flex items-center gap-3">
        <flux:button type="submit" variant="primary">
            {{ $merchant ? __('Save merchant') : __('Create merchant') }}
        </flux:button>
        <flux:button variant="ghost" :href="route('admin.merchants.index')" wire:navigate>
            {{ __('Cancel') }}
        </flux:button>
    </div>
</form>
