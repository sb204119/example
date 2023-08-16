<?php

namespace App\Http\Controllers;

use App\Address;
use App\Category;
use App\DigitalProduct;
use App\Dispute;
use App\Events\Purchase\ProductDisputeNewMessageSent;
use App\Exceptions\RedirectException;
use App\Exceptions\RequestException;
use App\Http\Controllers\BitcoinController;
use App\Http\Requests\Cart\MakePurchasesRequest;
use App\Http\Requests\Cart\NewItemRequest;
use App\Http\Requests\Profile\BecomeVendorRequest;
use App\Http\Requests\Profile\ChangeAddressRequest;
use App\Http\Requests\Profile\ChangeLocalCurrencyRequest;
use App\Http\Requests\Profile\NewTicketMessageRequest;
use App\Http\Requests\Profile\NewTicketRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\PGP\NewPGPKeyRequest;
use App\Http\Requests\PGP\StorePGPRequest;
use App\Marketplace\Utility\BitcoinConverter;
use App\Marketplace\Utility\Pay;
use App\Transfers;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Http\Requests\Product\{NewBasicRequest,
    NewShippingRequest,
    NewDigitalRequest,
    NewImageRequest,
    NewOfferRequest,
    NewProductRequest,
    NewShippingOptionsRequest
};
use App\Http\Requests\Purchase\LeaveFeedbackRequest;
use App\Http\Requests\Purchase\MakeDisputeRequest;
use App\Http\Requests\Purchase\NewDisputeMessageRequest;
use App\Http\Requests\Purchase\ResolveDisputeRequest;
use App\Marketplace\Cart;
use App\PhysicalProduct;
use App\Product;
use App\Purchase;
use App\Ticket;
use App\TicketReply;
use App\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{

    /**
     * Middleware that says user must be authenticated and 2fa verified
     *
     * ProfileController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('verify_2fa');
    }

    public function index()
    {
        return view('profile.index');
    }

    public function account()
    {
        $user = auth()->user();
        return view('profile.account', [
            'user' => $user
        ]);

    }

    public function ava(Request $request)
    {

        if ($request->hasFile('file')) {
            $user = auth()->user();
            $destinationPath = public_path('storage/avatars/');
            $fileName = $user->id . '.jpg';

            $request->file('file')->move($destinationPath, $fileName);
            $user->avatar = 'storage/avatars/' . $fileName;
            $user->save();
            return redirect()->route('profile.account');
        }
    }

    public static function deposit()
    {
        $address = app()->call([BitcoinController::class, 'getNewAddress']);
        $user = Auth::user();
        $deposit_from_db = Transfers::where('user_id', $user->id)->first();

        if ($deposit_from_db != null) {
            $spend = $deposit_from_db->created_at->addMinutes(1440)->lt(Carbon::now());
            $time = $deposit_from_db->created_at->format('Y-m-d H:i:s');
        } else {
            $spend = false;
            $model = new Transfers;
            $model->adress_pay = $address;
            $model->user_id = $user->id;
            $model->save();
        }
        if ($spend == true) {
            Transfers::where('user_id', $user->id)->delete();
            $model = new Transfers;
            $model->adress_pay = $address;
            $model->user_id = $user->id;
            $model->save();
        }

        $status_confrim = Pay::getTransaction();

        return view('profile.deposit', ['deposits' => $deposit_from_db->adress_pay, 'time' => $time]);

    }

    public function deposit_del()
    {
        $user = Auth::user();
        Transfers::where('user_id', $user->id)->delete();
        return redirect()->route('profile.account');
    }


    /**
     * Banned view
     *
     * @return Factory|View
     */
    public function banned()
    {
        if (auth()->user()->isBanned())
            $until = auth()->user()->bans()->orderByDesc('until')->first()->until;
        else
            return redirect()->route('profile.index');

        return view('profile.banned', [
            'until' => $until
        ]);
    }

    /**
     * Displays the page with the current pgp and the form to change pgp
     *
     * @return Factory|View
     */
    public function pgp()
    {
        return view('profile.pgp');
    }

    /**
     * Accepts the request for the new PGP key and generates data to confirm pgp
     *
     * @param NewPGPKeyRequest $request
     * @return RedirectResponse
     */
    public function pgpPost(NewPGPKeyRequest $request)
    {
        try {
            $request->persist();
        } catch (RequestException $e) {
            session()->flash('errormessage', $e->getMessage());
        }

        return redirect()->route('profile.pgp.confirm');
    }

    /**
     * Displays the page to confirm new PGP request
     *
     * @return Factory|View
     */
    public function pgpConfirm()
    {
        return view('profile.confirmpgp');
    }

    /**
     * Saves old key and sets new pgp key
     *
     * @param StorePGPRequest $request
     * @return RedirectResponse
     *
     */
    public function storePGP(StorePGPRequest $request)
    {
        try {
            $request->persist();
            session()->flash('success', 'You have successfully changed you PGP key.');
        } catch (RequestException $e) {
            session()->flash('errormessage', $e->getMessage());
            return redirect()->back();
        }

        return redirect()->route('profile.pgp');
    }

    /**
     * Page that displays old pgp keys
     *
     * @return Factory|View
     */
    public function oldpgp()
    {
        return view('profile.oldpgp',
            [
                'keys' => auth()->user()->pgpKeys()->orderByDesc('created_at')->get(),
            ]
        );
    }

    /**
     * Accepts request for changing password
     *
     * @param ChangePasswordRequest $request
     * @return RedirectResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $request->persist();
        } catch (RequestException $e) {
            session()->flash('errormessage', $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Turn 2fa on or off
     *
     * @param $turn
     * @return RedirectResponse
     *
     */
    public function change2fa($turn)
    {
        try {
            auth()->user()->set2fa($turn);
            session()->flash('success', 'You have changed you 2FA setting.');
        } catch (RequestException $e) {
            session()->flash('errormessage', $e->getMessage());
        }
        return redirect()->back();
    }

    /**
     * Become a Vendor page that has link for become a Vendor request
     *
     * @return Factory|View
     */
    public function become()
    {
        return view('profile.become', [
            'vendorFee' => config('marketplace.vendor_fee'),
            'depositAddresses' => auth()->user()->vendorPurchases
        ]);
    }

    /**
     * Make Vendor from the user
     *
     * @return RedirectResponse
     */
    public function becomeVendor(BecomeVendorRequest $request)
    {
        try {
            auth()->user()->becomeVendor($request->address);
            return redirect()->route('profile.vendor');
        } catch (RedirectException $e) {
            $e->flashError();
            return redirect($e->getRoute());
        } catch (RequestException $e) {
            session()->flash('errormessage', $e->getMessage());
        }
        return redirect()->back();
    }

    /**
     * Add product to the users wishlist
     *
     * @param Product $product
     * @return RedirectResponse
     */
    public function addRemoveWishlist(Product $product)
    {
        // Remove if it is added
        if (Wishlist::added($product, auth()->user())) {
            // removing
            Wishlist::getWish($product)->delete();
        } // add if it is not added
        else {
            $newWhish = new Wishlist([
                'product_id' => $product->id,
                'user_id' => auth()->user()->id,
            ]);

            $newWhish->save();
        }

        return redirect()->back();
    }

    /**
     * Returns the page with the product wishlist
     *
     * @return Factory|View
     */
    public function wishlist()
    {
        return view('profile.wishlist');
    }

    /**
     * Show the cart page
     *
     * @return Factory|View
     */
    public function cart()
    {
        return view('cart.index', [
            'items' => Cart::getCart()->items(),
            'numberOfItems' => Cart::getCart()->numberOfItems(),
            'totalSum' => Cart::getCart()->total(),
        ]);
    }

    /**
     * Add or edit item to cart
     *
     * @param NewItemRequest $request
     * @param Product $product
     * @return RedirectResponse
     */
    public function addToCart(NewItemRequest $request, Product $product)
    {
        try {
            $request->persist($product);
            session()->flash('success', 'You have added/changed an item!');

            return redirect()->route('profile.cart');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->back();
    }

    /**
     * Clear cart and return back
     *
     * @return RedirectResponse
     */
    public function clearCart()
    {
        session()->forget(Cart::SESSION_NAME);
        session()->flash('success', 'You have cleared your cart!');

        return redirect()->back();
    }

    /**
     * Remove $product from cart
     *
     * @param Product $product
     * @return RedirectResponse
     */
    public function removeProduct(Product $product)
    {
        Cart::getCart()->removeFromCart($product);
        session()->flash('You have removed a product.');

        return redirect()->back();
    }

    /**
     * Return table with checkout
     *
     * @return Factory|View
     */
    public function checkout()
    {
        return view('cart.checkout', [
            'items' => Cart::getCart()->items(),
            'totalSum' => Cart::getCart()->total(),
            'numberOfItems' => Cart::getCart()->numberOfItems(),

        ]);
    }

    /**
     * Commit purchases from cart
     *
     * @param MakePurchasesRequest $request
     * @return RedirectResponse
     */
    public function makePurchases(MakePurchasesRequest $request)
    {
        try {
            $request->persist();
        } catch (RequestException $e) {
            $e->flashError();
            return redirect()->back();
        }

        return redirect()->route('profile.purchases');
    }

    /**
     * Return all users purchases
     *
     * @return Factory|View
     */
    public function purchases($state = '')
    {
        $purchases = auth()->user()->purchases()->orderByDesc('created_at')->paginate(20);

        if (array_key_exists($state, Purchase::$states))
            $purchases = auth()->user()->purchases()->where('state', $state)->orderByDesc('created_at')->paginate(20);

        return view('profile.purchases.index', [
            'purchases' => $purchases,
            'state' => $state,
        ]);
    }

    /**
     * Return view for encrypted message
     *
     * @param Purchase $purchase
     * @return Factory|View
     */
    public function purchaseMessage(Purchase $purchase)
    {
        return view('profile.purchases.viewmessage', [
            'purchase' => $purchase
        ]);
    }

    /**
     * See purchase details
     *
     * @param Purchase $purchase
     * @return Factory|View
     */
    public function purchase(Purchase $purchase)
    {
        return view('profile.purchases.purchase', [
            'purchase' => $purchase
        ]);
    }

    /**
     * Show delivered confirmation page
     *
     * @param Purchase $purchase
     * @return Factory|View
     */
    public function deliveredConfirm(Purchase $purchase)
    {
        return view('profile.purchases.confirmdelivered', [
            'backRoute' => redirect()->back()->getTargetUrl(),
            'purchase' => $purchase,
        ]);
    }

    /**
     * Mark Purchase as Delivered
     *
     * @param Purchase $purchase
     * @return RedirectResponse
     */
    public function markAsDelivered(Purchase $purchase)
    {
        try {
            $purchase->delivered();
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->route('profile.purchases.single', $purchase);
    }

    /**
     * Returns view for confirming canceled
     *
     * @param Purchase $purchase
     * @return Factory|
     */
    public function confirmCanceled(Purchase $purchase)
    {
        return view('profile.purchases.confirmcanceled', [
            'backRoute' => redirect()->back()->getTargetUrl(),
            'sale' => $purchase
        ]);
    }

    /**
     * Make purchase as canceled
     *
     * @param Purchase $purchase
     * @return RedirectResponse
     */
    public function markAsCanceled(Purchase $purchase)
    {
        try {
            $purchase->cancel();
            session()->flash('success', 'You have successfully marked sale as canceled!');
        } catch (RequestException $e) {
            $e->flashError();
        }
        // if this logged user is vendor
        if ($purchase->isVendor())
            return redirect()->route('profile.sales.single', $purchase);
        return redirect()->route('profile.purchases.single', $purchase);
    }

    /**
     * Make Dispute for the given purchase
     *
     * @param MakeDisputeRequest $request
     * @param Purchase $purchase
     * @return RedirectResponse
     */
    public function makeDispute(MakeDisputeRequest $request, Purchase $purchase)
    {
        try {
            $purchase->makeDispute($request->message);
            session()->flash('success', 'You have made a dispute for this purchase!');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->back();
    }

    /**
     * Send new dispute message to the dispute
     *
     * @param NewDisputeMessageRequest $request
     * @param Dispute $dispute
     * @return RedirectResponse
     */
    public function newDisputeMessage(NewDisputeMessageRequest $request, Dispute $dispute)
    {
        try {
            $dispute->newMessage($request->message);
            event(new ProductDisputeNewMessageSent($dispute->purchase, auth()->user()));
            session()->flash('success', 'You have successfully posted new message for dispute!');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->back();
    }


    /**
     * Leaving feedback
     *
     * @param LeaveFeedbackRequest $request
     * @param Purchase $purchase
     * @return RedirectResponse
     */
    public function leaveFeedback(LeaveFeedbackRequest $request, Purchase $purchase)
    {
        try {
            $request->persist($purchase);
            session()->flash('success', 'You have left your feedback!');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->route('profile.purchases.single', $purchase);
    }

    /**
     * Change vendor's address
     *
     * @param ChangeAddressRequest $request
     * @return RedirectResponse
     */
    public function changeAddress(ChangeAddressRequest $request)
    {
        try {
            auth()->user()->setAddress($request->address, $request->coin);
            session()->flash('success', 'You have successfully changed your address!');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->back();
    }

    /**
     * Remove address of the logged user with given $id
     *
     * @param $id
     * @return RedirectResponse
     */
    public function removeAddress($id)
    {
        try {
//            $address = Address::findOrFail($id);
            // Check for number of addresses for coin
//            if(auth() -> user() -> numberOfAddresses($address -> coin) <= 1)
//                throw new RequestException('You must have at least one address for each coin!');
//
            auth()->user()->addresses()->where('id', $id)->delete();
            session()->flash('success', 'You have successfully removed your address!');
        } catch (RequestException $e) {
            $e->flashError();
        }

        return redirect()->back();
    }

    /**
     * Showing all tickets
     *
     * @param Ticket|null $ticket
     * @return Factory|View
     */
    public function tickets(Ticket $ticket = null)
    {
        // Tickets
        if (!is_null($ticket)) {
            $replies = $ticket->replies()->orderByDesc('created_at')->paginate(config('marketplace.products_per_page'));
        } else {
            $replies = collect(); // empty collection
        }


        return view('profile.tickets', [
            'replies' => $replies,
            'ticket' => $ticket
        ]);
    }

    /**
     * Opens new Ticket form
     *
     * @param NewTicketRequest $request
     */
    public function newTicket(NewTicketRequest $request)
    {
        try {
            $newTicket = Ticket::openTicket($request->title);
            TicketReply::postReply($newTicket, $request->message);

            return redirect()->route('profile.tickets', $newTicket);
        } catch (RequestException $e) {
            Log::error($e->getMessage());
            session()->flash('errormessage', $e->getMessage());
        }
    }

    public function newTicketMessage(Ticket $ticket, NewTicketMessageRequest $request)
    {
        try {
            TicketReply::postReply($ticket, $request->message);
        } catch (RequestException $e) {
            Log::error($e);
            session()->flash('errormessage', $e->getMessage());
        }
        return redirect()->back();
    }


}
