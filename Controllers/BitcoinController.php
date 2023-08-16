<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BitcoinController extends Controller
{

    /**
     * Create wallet
     * Tinker: app()->call([$controller, 'createWallet'], ['name'=> 'testwallet']);
     *  @returns JsonResponse
     */
    public static function createWallet(string $name)
    {
        $response = bitcoind()->createWallet($name);
        return $response->get();
    }

    /**
     * Generate blocks in the blockchain
     * Tinker:  app()->call([$controller, 'generateBlocksToAddress'], ['nblocks'=>101]);
     *  @returns JsonResponse
     */
    public function generateBlocksToAddress(int $nblocks)
    {
        $address = bitcoind()->getNewAddress();
        $response = bitcoind()->generateToAddress($nblocks,$address->get());
        return $response->get();
    }

    /**
     * Get balances
     * Tinker: app()->call([$controller, 'getCurrentBalance']);
     * @returns float as the balance
     */
    public function getCurrentBalance()
    {
        $balance = bitcoind()->getBalance();
        return $balance->get();
        //return view('welcome')->with('balance', $balance->get());
    }

    /**
     * List unspent
     * Tinker: app()->call([$controller, 'listUnspent']);
     * @returns float as the balance
     */
    public static function listUnspent()
    {
        $unspent = bitcoind()->listUnspent();
        //dd(response()->json($balance->get()));
        return $unspent->get();
    }

    /**
     * Send bitcoins to an address
     * Tinker: app()->call([$controller, 'sendToAddress'], ['amount'=>10.00]);
     * @returns string as hex which is the txid
     */
    public function sendToAddress(Request $amount)
    {
        $address = bitcoind()->getNewAddress();
        $txid = bitcoind()->sendToAddress($address->get(),$amount['amount']);
        //dd(response()->json($balance->get()));
        return $txid->get();
    }

    /**
     * Get new address
     * Tinker: app()->call([$controller, 'getNewAddress']);
     * @returns string with the address
     */
    public static function getNewAddress()
    {
        $address = bitcoind()->getNewAddress();
        return $address->get();
    }

    public static function test()
    {
        $res = app()->call([BitcoinController::class, 'listUnspent']);
        dd($res);
    }
}
