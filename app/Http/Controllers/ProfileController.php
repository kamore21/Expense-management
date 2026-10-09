<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Locales;
use Symfony\Polyfill\Intl\Icu\Exception\NotImplementedException;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $countries = $this->localizedNames($user->locale, fn (string $locale): array => Countries::getNames($locale));
        $currencies = array_intersect_key(
            $this->localizedNames($user->locale, fn (string $locale): array => Currencies::getNames($locale)),
            array_flip(Currencies::getCurrencyCodes()),
        );
        $locales = Locales::getNames('en');
        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);
        asort($currencies, SORT_NATURAL | SORT_FLAG_CASE);
        asort($locales, SORT_NATURAL | SORT_FLAG_CASE);

        return view('profile.edit', [
            'user' => $user,
            'countries' => $countries,
            'currencies' => $currencies,
            'locales' => $locales,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'currencyLocked' => $user->expenses()->exists() || $user->invoices()->exists() || $user->budgets()->exists(),
        ]);
    }

    /**
     * Resolve translated display names for the user's locale, falling back to
     * English when the ICU polyfill cannot collate the requested locale.
     *
     * @param  callable(string): array<string, string>  $lookup
     * @return array<string, string>
     */
    private function localizedNames(string $locale, callable $lookup): array
    {
        try {
            return $lookup($locale);
        } catch (NotImplementedException|MissingResourceException) {
            return $lookup('en');
        }
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
