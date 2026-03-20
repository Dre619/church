<?php

use App\Models\OrganizationPlan;
use App\Models\Plan;
use App\Models\Projects;
use App\Models\User;
function getUserData($id){
    $user = User::where('id', $id)->first();
    return $user;
}

if (!function_exists('nice_file_name')) {
    function nice_file_name($file_title = "", $extension = "")
    {
        return slugify($file_title) . '-' . time() . '.' . $extension;
    }
}

if (!function_exists('slugify')) {
    function slugify($string)
    {
        // Convert spaces or consecutive dashes to single dashes
        $slug = preg_replace('/\s+/u', '-', $string);  // Replace spaces with hyphens
        $slug = preg_replace('/-+/', '-', $slug);  // Replace multiple hyphens with a single one

        // Remove unwanted characters, but allow all Unicode letters (from any language) and numbers
        $slug = preg_replace('/[^\p{L}\p{N}-]+/u', '', $slug);  // \p{L} matches any letter in any language, \p{N} matches any number

        // Trim hyphens from the start and end
        $slug = trim($slug, '-');

        return $slug;
    }
}

/** @return array<string, string> */
function currency_list(): array
{
    return [
        'ZMW' => 'Zambian Kwacha (ZK)',
        'NGN' => 'Nigerian Naira (₦)',
        'USD' => 'US Dollar ($)',
        'GBP' => 'British Pound (£)',
        'EUR' => 'Euro (€)',
        'KES' => 'Kenyan Shilling (KSh)',
        'GHS' => 'Ghanaian Cedi (₵)',
        'ZAR' => 'South African Rand (R)',
        'UGX' => 'Ugandan Shilling (USh)',
        'TZS' => 'Tanzanian Shilling (TSh)',
        'RWF' => 'Rwandan Franc (RWF)',
        'MWK' => 'Malawian Kwacha (MWK)',
        'ETB' => 'Ethiopian Birr (ETB)',
        'CAD' => 'Canadian Dollar (CA$)',
        'AUD' => 'Australian Dollar (A$)',
    ];
}

/** @return array<string, string> */
function currency_symbols(): array
{
    return [
        'NGN' => '₦',
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'KES' => 'KSh ',
        'GHS' => '₵',
        'ZAR' => 'R ',
        'ZMW' => 'K',
        'UGX' => 'USh ',
        'TZS' => 'TSh ',
        'RWF' => 'RWF ',
        'MWK' => 'MWK ',
        'ETB' => 'ETB ',
        'CAD' => 'CA$',
        'AUD' => 'A$',
    ];
}

/**
 * Return the active Plan for the authenticated user's organization.
 * Returns null if the user has no organization or no active subscription.
 */
function active_plan(): ?Plan
{
    $orgUser = auth()->user()?->myOrganization;

    if (! $orgUser) {
        return null;
    }

    return OrganizationPlan::with('plan')
        ->where('organization_id', $orgUser->organization_id)
        ->where('is_active', true)
        ->where('end_date', '>=', now())
        ->latest('end_date')
        ->first()
        ?->plan;
}

function format_currency(float|int|string $amount, string $currency = 'ZMW'): string
{
    $symbol = currency_symbols()[$currency] ?? ($currency . '');

    return $symbol . number_format((float) $amount, 2);
}

function getProjectData($id)
{
    if($id == 0)
    {
        return false;
    }
    $project = Projects::where('id', $id)->first();
    if($project){
    return $project;
    }else{
        return false;
    }
}
