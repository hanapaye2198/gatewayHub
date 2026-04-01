<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Contracts\View\View;

/**
 * Public landing pages after Coins hosted checkout redirects.
 */
class PaymentRedirectController extends Controller
{
    public function success(string $transaction): View
    {
        return $this->render($transaction, 'success');
    }

    public function failure(string $transaction): View
    {
        return $this->render($transaction, 'failure');
    }

    public function cancel(string $transaction): View
    {
        return $this->render($transaction, 'cancel');
    }

    public function default(string $transaction): View
    {
        return $this->render($transaction, 'default');
    }

    private function render(string $transaction, string $outcome): View
    {
        $payment = Payment::query()->findOrFail($transaction);

        return view('payment.checkout-result', [
            'payment' => $payment,
            'outcome' => $outcome,
        ]);
    }
}
