<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <fieldset class="space-y-4 border-t border-gray-200 pt-5">
            <legend class="text-sm font-semibold text-gray-800">Regional settings</legend>
            <p class="text-xs text-gray-600">Country, currency, dates, and time zone for this account.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="country_code" value="Country or region" />
                    <select id="country_code" name="country_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach ($countries as $code => $country)
                            <option value="{{ $code }}" @selected(old('country_code', $user->country_code) === $code)>{{ $country }} ({{ $code }})</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('country_code')" />
                </div>

                <div>
                    <x-input-label for="currency_code" value="Account currency" />
                    @if ($currencyLocked)
                        <input type="hidden" name="currency_code" value="{{ $user->currency_code }}">
                    @endif
                    <select id="currency_code" name="currency_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @disabled($currencyLocked) required>
                        @foreach ($currencies as $code => $currency)
                            <option value="{{ $code }}" @selected(old('currency_code', $user->currency_code) === $code)>{{ $code }} · {{ $currency }}</option>
                        @endforeach
                    </select>
                    @if ($currencyLocked)
                        <p class="mt-1 text-xs text-gray-500">Locked after financial records are created to prevent changing their meaning.</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('currency_code')" />
                </div>

                <div>
                    <x-input-label for="locale" value="Formatting locale" />
                    <select id="locale" name="locale" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach ($locales as $code => $localeName)
                            <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $localeName }} · {{ $code }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('locale')" />
                </div>

                <div>
                    <x-input-label for="timezone" value="Time zone" />
                    <select id="timezone" name="timezone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $user->timezone) === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('timezone')" />
                </div>
            </div>
        </fieldset>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
