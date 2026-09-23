@php
    /** @var \App\Models\User|null $platformAdmin */
    $platformAdmin = $platformAdmin ?? null;
@endphp

<form
    method="POST"
    action="{{ $platformAdmin ? route('admin.administrators.update', $platformAdmin) : route('admin.administrators.store') }}"
    class="flex max-w-xl flex-col gap-5"
>
    @csrf
    @if ($platformAdmin)
        @method('PUT')
    @endif

    <flux:field>
        <flux:label>{{ __('Role') }}</flux:label>
        <flux:input type="text" value="{{ __('Admin') }}" disabled />
    </flux:field>

    <flux:field>
        <flux:label for="name">{{ __('Name') }}</flux:label>
        <flux:input
            id="name"
            name="name"
            type="text"
            :value="old('name', $platformAdmin?->name)"
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
            :value="old('email', $platformAdmin?->email)"
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
            :required="! $platformAdmin"
            viewable
        />
        @if ($platformAdmin)
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
            :required="! $platformAdmin"
            viewable
        />
    </flux:field>

    @php
        $activeValue = old('is_active', $platformAdmin?->is_active ?? true);
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

    <div class="flex items-center gap-3">
        <flux:button type="submit" variant="primary">
            {{ $platformAdmin ? __('Save administrator') : __('Create administrator') }}
        </flux:button>
        <flux:button variant="ghost" :href="route('admin.administrators.index')" wire:navigate>
            {{ __('Cancel') }}
        </flux:button>
    </div>
</form>
