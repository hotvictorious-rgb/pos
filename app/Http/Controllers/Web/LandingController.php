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

        $plans = config('saas.plans', [
            'basic' => [
                'name' => 'Starter Plan',
                'max_branches' => 1,
                'max_users' => 3,
                'price_monthly' => 15000,
            ],
            'pro' => [
                'name' => 'Professional Growth',
                'max_branches' => 5,
                'max_users' => 15,
                'price_monthly' => 35000,
            ],
            'enterprise' => [
                'name' => 'Enterprise Multi-Branch',
                'max_branches' => 999,
                'max_users' => 999,
                'price_monthly' => 75000,
            ],
        ]);

        $trialDays = config('saas.trial_days', 14);

        return view('landing', compact('plans', 'trialDays'));
    }
}
