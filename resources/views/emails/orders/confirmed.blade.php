<h1>Order Confirmed</h1>

<p>Order No: {{ $order->order_no }}</p>
<p>Product: {{ $order->product->name }}</p>
<p>Status: {{ $order->status }}</p>
<p>Total: {{ number_format($order->price, 2) }}</p>
