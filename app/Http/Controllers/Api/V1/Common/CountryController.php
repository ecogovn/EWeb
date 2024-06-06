<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Models\Country;
use App\Http\Controllers\ApiController;
use App\Transformers\CountryTransformer;
use App\Transformers\CountryNewTransformer;
use App\Models\Admin\Onboarding;

use App\Transformers\OnboardingTransformer;

/**
 * @group Countries
 *
 * Get countries
 */
class CountryController extends ApiController
{
    /**
     * Get all the countries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $countriesQuery = Country::active();

        $countries = filter($countriesQuery, new CountryTransformer)->defaultSort('name')->get();

        return $this->respondOk($countries);
    }
    public function indexNew()
{
    $countriesQuery = Country::active();
    $onboardingQuery = Onboarding::active();
    $countries = filter($countriesQuery, new CountryNewTransformer)->defaultSort('name')->get();
    $onboarding = filter($onboardingQuery, new OnboardingTransformer)->defaultSort('title')->get();
    $response = [
        'countries' => $countries,
        'onboarding' => $onboarding
    ];

    return $this->respondOk($response);
}

}
