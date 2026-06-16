<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{

public function checkout()
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => config('services.stripe.Payment_ID'),
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => url('/payment-success'),
            'cancel_url' => url('/payment-cancel'),
        ]);

        return response()->json([
            'url' => $session->url,
        ]);
    }

    public function webhook(Request $request)
{
    Log::info('Stripe webhook received');

$payload = $request->getContent();
$sigHeader = $request->header('Stripe-Signature');
$endpointSecret = config('services.stripe.webhook_secret');

try {

    $event = Webhook::constructEvent(
        $payload,
        $sigHeader,
        $endpointSecret
    );

    Log::info('Stripe signature verified', [
        'event_type' => $event->type
    ]);

} catch (\Exception $e) {

    Log::error('Stripe webhook verification failed', [
        'error' => $e->getMessage()
    ]);

    return response()->json([
        'error' => 'Invalid webhook'
    ], 400);
}

if ($event->type === 'checkout.session.completed') {

    Log::info('Checkout session completed');

    $session = $event->data->object;

    Log::info('Session details', [
        'session_id' => $session->id ?? null,
        'customer_email' => $session->customer_email ?? null,
        'payment_intent' => $session->payment_intent ?? null
    ]);

    $user = \App\Models\User::where(
        'email',
        $session->customer_email
    )->first();

    if (!$user) {

        Log::warning('User not found', [
            'email' => $session->customer_email
        ]);

        return response()->json([
            'error' => 'User not found'
        ]);
    }

    Log::info('User found', [
        'user_id' => $user->id,
        'email' => $user->email
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'payment_id' => $session->id,
        'transaction_id' => $session->payment_intent,
        'amount' => 4.99,
        'currency' => 'GBP',
        'payment_method' => 'stripe',
        'status' => 'paid',
        'is_subscription' => true,
        'start_date' => now(),
        'end_date' => now()->addMonth(),
    ]);

    Log::info('Payment record created', [
        'payment_db_id' => $payment->id,
        'stripe_session_id' => $session->id
    ]);
}

Log::info('Webhook processing finished');

return response()->json([
    'success' => true
]);
}

    /*
    |--------------------------------------------------------------------------
    | CREATE PAYMENT
    |--------------------------------------------------------------------------
    */

    public function store(Request $request) 
    {
      

        $validated = $request->validate([


            'amount' => 'required|numeric|min:1',

            'payment_method' => 'required|in:stripe,paypal,apple_pay,google_pay',

            'payment_id' => 'nullable|string',

            'transaction_id' => 'nullable|string',

            'currency' => 'nullable|string',

            'is_subscription' => 'nullable|boolean',

            'start_date' => 'nullable|date',

            'end_date' => 'nullable|date',


        ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE PAYMENT
        |--------------------------------------------------------------------------
        */

        $payment = Payment::create([

            'user_id' => Auth::id(),

            'payment_id' => $validated['payment_id'] ?? null,

            'transaction_id' => $validated['transaction_id'] ?? null,

            'amount' => $validated['amount'],

            'currency' => $validated['currency'] ?? 'GBP',

            'payment_method' => $validated['payment_method'],

            'status' => 'paid',

            'is_subscription' =>  true,

            'start_date' =>  now(),

            'end_date' =>
                now()->addMonth(),


        ]);

        return response()->json([

            'success' => true,

            'message' => 'Payment created successfully',

            'payment' => $payment,

        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT HISTORY
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $payments = Payment::where(
            'user_id',
            Auth::id()
        )
        ->latest()
        ->get();

        return response()->json([

            'success' => true,

            'payments' => $payments,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE PAYMENT
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $payment = Payment::where(
            'user_id',
            Auth::id()
        )
        ->findOrFail($id);

        return response()->json([

            'success' => true,

            'payment' => $payment,

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    public function subscription()
    {
        $subscription = Payment::where(
            'user_id',
            Auth::id()
        )
        ->where('status', 'paid')
        ->where('end_date', '>', now())
        ->latest()
        ->first();

        return response()->json([

            'success' => true,

            'has_subscription' => !!$subscription,

            'subscription' => $subscription,

        ]);
    }
}