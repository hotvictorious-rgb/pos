<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Display the rich Nigerian-tailored SaaS landing page.
     */
    public function index(Request $request)
    {
        if (\Illuminate\Support\Facades\Auth::check() || session('user_id')) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return redirect()->route('saas.admin.index');
            }
            return redirect()->route('dashboard');
        }

        $priceBasic = (float) \App\Models\SaaSSetting::get('monthly_price_basic', config('saas.plans.basic.price_monthly', 15000));
        $pricePro = (float) \App\Models\SaaSSetting::get('monthly_price_pro', config('saas.plans.pro.price_monthly', 35000));
        $priceEnterprise = (float) \App\Models\SaaSSetting::get('monthly_price_enterprise', config('saas.plans.enterprise.price_monthly', 75000));
        $trialDays = (int) \App\Models\SaaSSetting::get('trial_days', config('saas.trial_days', 14));
        $currency = \App\Models\SaaSSetting::get('currency_symbol', '₦');

        $plans = [
            'basic' => [
                'name' => config('saas.plans.basic.name', 'Starter Plan'),
                'max_branches' => config('saas.plans.basic.max_branches', 1),
                'max_users' => config('saas.plans.basic.max_users', 3),
                'price_monthly' => $priceBasic,
            ],
            'pro' => [
                'name' => config('saas.plans.pro.name', 'Professional Growth'),
                'max_branches' => config('saas.plans.pro.max_branches', 5),
                'max_users' => config('saas.plans.pro.max_users', 15),
                'price_monthly' => $pricePro,
            ],
            'enterprise' => [
                'name' => config('saas.plans.enterprise.name', 'Enterprise Multi-Branch'),
                'max_branches' => config('saas.plans.enterprise.max_branches', 999),
                'max_users' => config('saas.plans.enterprise.max_users', 999),
                'price_monthly' => $priceEnterprise,
            ],
        ];

        return view('landing', compact('plans', 'trialDays', 'currency'));
    }
}
