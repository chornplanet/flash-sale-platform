<?php

// app/Mail/OrderConfirmedMail.php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

class OrderConfirmedMail extends Mailable
{
    public function __construct(
        public Order $order
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Your order has been confirmed')
            ->view('emails.orders.confirmed');
    }
}
