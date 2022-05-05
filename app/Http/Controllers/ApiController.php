<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\MessageLog;

class ApiController extends Controller
{
    // 通知LINE用戶
    static public function line_message( $post_data )
    {
        $post_data = json_encode($post_data);
        $url       = env('SLP_API').'/api/line/message/send'; 
        // $url       = 'https://beapi.shilipai.com.tw/api/line/message/send'; 
        // $result    = Func::post_curl($url,$post_data);

        $ch = curl_init($url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($post_data)));
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result);

        if($result && $result->status==false){
            return ['status'=>false, 'data' => $result ,'msg'=>isset($result->message)?$result->message:$result->msg];
        }
        
        return ['status'=>true, 'data'=>$result];
    }

    // FB回傳使用者刪除應用程式
    public function member_remove_fb()
    {
        $signed_request = request('signed_request');
        $data = Self::parse_signed_request($signed_request);
        $user = Customer::where('facebook_id', $data['user_id'])->first();
        if ($user) {
            $user->facebook_id = NULL;
            $user->save();

            $data = array(
                    'url' => url(request()->path()) . '/deletion?id=' . $user->id,
                    'confirmation_code' => $user->id,
                );

            return response()->json($data);
        } else {
            $rand = strtotime(date('Y-m-d H:i:s'));
            $data = array(
                'url' => url(request()->path()) . '/deletion?id=shilipai' . $rand,
                'confirmation_code' => 'shilipai' . $rand,
            );

            return response()->json($data);
        }
    }

    static public function parse_signed_request($signed_request)
    {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        $secret = config('services.facebook.app_secret'); // Use your app secret here
        // decode the data
        $sig  = Self::base64_url_decode($encoded_sig);
        $data = json_decode(Self::base64_url_decode($payload), true);

        // confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig
        ) {
            error_log('Bad Signed JSON signature!');
            return null;
        }

        return $data;
    }

    static public function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

}
