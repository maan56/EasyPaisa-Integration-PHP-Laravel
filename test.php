<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class DepositController extends Controller
{
  // Sandbox Url's
    protected $transaction_url_1 = 'https://easypaystg.easypaisa.com.pk/easypay/Index.jsf';
    protected $transaction_url_2 = 'https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf';
    
    protected $storeId = '11573';
    protected $hashKey = 'YS8399Z0C7SUT3PM';
    
    // This function will render view of checkout form
    public function checkoutIndex($uid, $transactionId, $mobileNo)
    {
      return Inertia::render('Checkout/index', [
            'uid' => $uid,
            'mobileNo' => $mobileNo,
            'transactionId' => $transactionId,
        ]);
        
        //these variables will be added to checkout form POST request.
    }
    
    // This function will precess form request and render view to confirm checkout form
    public function checkout(Request $request)
    {
      $post_back_url_1 = "https://saqb.pk/checkout/" . $request->uid . "/" . $request->transactionId . "/" . $request->mobileNo . "/confirm";
      $date = Carbon::now();
      $expiryDate = $date->addHour()->format('Ymd His');  //YYYYMMDD HHMMSS
      $post_data = array(
            "storeId" => $this->storeId,
            "amount" => $request->amount . '.0',
            'postBackURL' => $post_back_url_1,
            'orderRefNum' => $request->transactionId,
            'expiryDate' => $expiryDate,
            'merchantHashedReq' => '',
            'autoRedirect' => '1',
            'paymentMethod' => 'MA_PAYMENT_METHOD',
            'mobileNum' => $request->mobileNo,
        );
        
        $sorted_string = "amount=" . $post_data['amount'] . "&";
        $sorted_string .= "autoRedirect=" . $post_data['autoRedirect'] . "&";
        $sorted_string .= "expiryDate=" . $post_data['expiryDate'] . "&";
        $sorted_string .= "mobileNum=" . $post_data['mobileNum'] . "&";
        $sorted_string .= "orderRefNum=" . $post_data['orderRefNum'] . "&";
        $sorted_string .= "paymentMethod=" . $post_data['paymentMethod'] . "&";
        $sorted_string .= "postBackURL=" . $post_data['postBackURL'] . "&";
        $sorted_string .= "storeId=" . $post_data['storeId'];
        
        $cipher = "aes-128-ecb";
        $crypttext = openssl_encrypt($sorted_string, $cipher, $this->hashKey, OPENSSL_RAW_DATA);
        $hashRequest = base64_encode($crypttext);
        
        $post_data['merchantHashedReq'] = $hashRequest;
        
        return Inertia::render('Checkout/checkout', [
            'amount' => $post_data['amount'],
            'storeId' => $this->storeId,
            'mobileNum' => $post_data['mobileNum'],
            'expiryDate' => $expiryDate,
            'orderRefNum' => $post_data['orderRefNum'],
            'autoRedirect' => $post_data['autoRedirect'],
            'paymentMethod' => $post_data['paymentMethod'],
            'postBackURL' => $post_back_url_1,
            'merchantHashedReq' => $hashRequest,
            'transaction_url_1' => $this->transaction_url_1,
        ]);
        // At this moment we have all request including Hashed request data. which will posted
        // to index url of easypaisa (provided by easypaisa) and return auth_token and postBackURL
    }
    
    // This function will run when easypaisa api page redirects to postBackURL in request.
    public function checkoutConfirm(Request $request, $uid, $transactionId, $mobileNo)
    {
      // $uid, $transactionId, $mobileNo are the variables in request URL and we use them to generate $post_back_url_2
      if ($request->has('auth_token')) {
            // if the request is successful, it will return auth_token. and if request has auth_token, this code block will run.
            $auth_token = $request->auth_token;
            $post_back_url_2 = "https://saqb.pk/checkout/" . $uid . "/" . $transactionId . "/" . $mobileNo . "/paid";
            
            return Inertia::render('Checkout/confirm', [
                'auth_token' => $auth_token,
                'postBackURL' => $post_back_url_2,
                'transaction_url_2' => $this->transaction_url_2,
                ]);
            // it will render checkout confirm form view.
        }
        else {
          // if the request is not successful and transaction is not authorized, this code block will run.
          // it will redirect user to checkout fail page with error.
            return redirect()->route('checkout.transaction.fail', [
                'uid' => $uid,
                'mobileNo' => $mobileNo,
                'transactionId' => $transactionId,
            ]);
        }
    }
    
    public function checkoutSuccess(Request $request, $uid, $transactionId, $mobileNo, $amount)
    {
    //if the transaction is successful, it will return an array with index "desc".
      if ($request->has('desc') && $request->desc == "0000") {
            // redirect to final close page with amount
            return redirect()->route('checkout.transaction.success', [
                'uid' => $uid,
                'amount' => $amount,
                'mobileNo' => $mobileNo,
                'transactionId' => $transactionId,
            ]);
        } else {
            // redirect to error page then close.
            return redirect()->route('checkout.transaction.fail', [
                'uid' => $uid,
                'mobileNo' => $mobileNo,
                'transactionId' => $transactionId,
            ]);
        }
    }

}
