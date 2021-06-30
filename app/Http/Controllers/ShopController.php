<?php

namespace App\Http\Controllers;

use App\City;
use App\Http\Resources\Cities;
use App\Http\Resources\Provinces;
use App\Order;
use App\BookOrder;
use App\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Book;
use Illuminate\Support\Facades\DB;


class ShopController extends Controller
{
    //ambil data provinsi
    public function provinces()
    {
        return new Provinces(Province::get());
    }

    //ambil data kota
    public function cities()
    {
        return new Cities(City::get());
    }

    //memproses orderan dari user

    public function shipping(Request $request)
    {
        $user = Auth::user();
        $status = "error";
        $message = "";
        $data = null;
        $code = 200;
        if($user){
            $this->validate($request, [
                'name' => 'required',
                'address' => 'required',
                'phone' => 'required',
                'province_id' => 'required',
                'city_id' => 'required'
            ]);
            $user->name = $request->name;
            $user->address = $request->address;
            $user->phone = $request->phone;
            $user->province_id = $request->province_id;
            $user->city_id = $request->city_id;

            if($user->save())
            {
                $status = "success";
                $message = "Sukses memperbarui pengiriman";
                $data = $user->toArray();
            }
            else{
                $message = "Gagal update pengiriman";
            }
        }
        else{
            $message = "User tidak ditemukan";
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    //ambil data kurir
    public function couriers()
    {
        $couriers = [
            ['id' => 'jne', 'text' => 'JNE'],
            ['id' => 'tiki', 'text' => 'TIKI'],
            ['id' => 'pos', 'text' => 'POS']
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'couriers',
            'data' => $couriers
        ]);
    }

    //menghitung berat barat dan ongkos kirim
    public function services(Request $request)
    {
        $status ="error";
        $data = [];
        $message = "";
        $this->validate($request, [
            'courier' => 'required',
            'carts' => 'required'
        ]);

        $user = Auth::user();
        if($user)
        {
            $destination = $user->city_id;
            if($destination>0)
            {
                $origin = 153; //Jakarta Selatan
                $courier = $request->courier;
                $carts = $request->carts;
                //transformasi dari json menjadi array
                $carts = json_decode($carts, true);

                //validasi data belanja, panggil method validateCarts
                $validCart = $this->validateCart($carts);
                $data['safe_carts'] = $validCart['safe_carts'];
                $data['total'] = $validCart['total'];
                $quantity_different = $data['total']['quantity_before']<>$data['total']['quantity'];
                //ubah berat gram ke kilogram
                $weight = $validCart['total']['weight']* 1000;

                if ($weight > 0){
                    //request courier service API RajaOngkir
                    $parameter = [
                        'origin' => $origin,
                        'destination' => $destination,
                        'weight' => $weight,
                        'courier' => $courier
                    ];

                    //cek ongkos kirim ke api RajaOngkir melalui fungsi getServices
                    $respon_services = $this->getServices($parameter);
                    if($respon_services['error'] == null)
                    {
                        $services = [];
                        $response = json_decode($respon_services['response']);
                        //cek biaya ke rajaongkir
                        $biaya = $response->rajaongkir->results[0]->costs;
                        foreach($biaya as $cost)
                        {
                            //parsing ongkos kirimnya
                            $service_name = $cost->service;
                            $service_cost = $cost->cost[0]->value;
                            $service_estimation = str_replace('hari', '', trim($cost->cost[0]->etd));

                            $services[] =[
                                'service' => $service_name,
                                'cost' => $service_cost,
                                'estimation' => $service_estimation,
                                'resume' => $service_name .' [ Rp .'.number_format($service_cost).' , Estimasi sampai tujuan '
                                .$cost->cost[0]->etd.' hari ]'
                            ];
                        }

                        //Response
                        if(count($services) > 0)
                        {
                            $data['services'] = $services;
                            $status = 'success';
                            $message = "ambil data ongkir berhasil";
                        }
                        else{
                            $message = "data ongkir tidak tersedia";
                        }

                        //ketika jumlah beli lebih besar dari jumlah stok,
                        //tampilkann warning
                        if($quantity_different){
                            $status = "warning";
                            $message = "Check cart data, ".$message;
                        }
                    }
                    else{
                        $message = "Curl Error #: " .$respon_services['error'];
                    }
                }
                else {
                        $message = "weight invalid";
                }
            }
            else{
                $message = "destination not set";
            }
        }
        else{
            $message = "User tidak ditemukan";
        }

        return response()->json([
            'status' => $status,
            'message' =>$message,
            'data' => $data
        ], 200);
    }

    //validasi kelengkapan data
    protected function validateCart($carts)
    {
        $safe_carts = []; //untuk menampung data yang lolos
        //inisial data
        $total = [
            'quantity_before' => 0,
            'quantity' => 0,
            'price' => 0,
            'weight' => 0
        ];

        $idx=0;

        //looping data state carts yang dikirim ke server untuk memastikan data valid
        foreach($carts as $cart){
            $id = (int)$cart['id'];
            $quantity = (int)$cart['quantity'];
            $total['quantity_before'] += $quantity;
            $book = Book::findOrFail($id); //ambil data buku berdarkan ID
            //jika buku ada
            if($book){
                //jika stok buku lebih dari 0
                if($book->stock > 0)
                {
                    //simpan data2 buku ke dalam array safe_carts
                    $safe_carts[$idx]['id'] = $book->id;
                    $safe_carts[$idx]['title'] = $book->title;
                    $safe_carts[$idx]['cover'] = $book->cover;
                    $safe_carts[$idx]['price'] = $book->price;
                    $safe_carts[$idx]['weight'] = $book->weight;
                    //jika jumlah stok buku kurang dari kuantitas buku yg diminta user
                    if($book->stock < $quantity){
                        $quantity = (int)$book->stock; //ubah quantity jadi dengan jumlah stok sekarang
                    }
                    $safe_carts[$idx]['quantity'] = $quantity;

                    //tambahkan ke array total
                    $total['quantity'] += $quantity;
                    $total['price'] += $book->price * $quantity;
                    $total['weight'] += $book->weight * $quantity;
                    $idx++;
                }
                else{
                    continue;
                }
            }
        }

        return [
            'safe_carts' => $safe_carts,
            'total' => $total
        ];
    }

    //ambil data ongkir dari API Rajaongkir
    public function getServices($data)
    {
        $url_cost = "https://api.rajaongkir.com/starter/cost";
        $key = "bde360fb1f7ec2eb275da40b81bf4347";
        $postdata = http_build_query($data);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url_cost,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded",
                "key: ".$key
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        return [
            'error' => $error,
            'response' => $response
        ];
    }

    //menampilkan list pemesanan user terkait
    public function myOrder(Request $request)
    {
        $user = Auth::user();
        $status = "error";
        $message = "";
        $data = [];
        if($user){
            $orders = Order::where('user_id', $user->id)
            ->orderBy('id', 'DESC')->get();

            $status = "success";
            $message = "data my order";
            $data = $orders;
        }
        else{
            $message = "User not found";
        }
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], 200);
    }

    //method untuk menangani proses payment
    public function payment (Request $request)
    {
        $error = 0;
        $status = "error";
        $message = "";
        $data = [];

        if(Auth::user()){
            //validasi courier, service, dan carts
            $this->validate($request, [
            'courier' => 'required',
            'service' => 'required',
            'carts' => 'required'
            ]);

            DB::beginTransaction();
            try{
                //siapkan data alamat toko, tujuan, dan carts
                $origin = 153; //Jakarta Selatan
                $destination = Auth::user()->city_id;
                if($destination<=0) $error++;
                $courier = $request->courier;
                $service = $request->service;
                $carts = json_decode($request->carts, true);

                /*
                buat data order untuk disimpan di database
                */
                $order = new Order();
                $order->user_id = Auth::user()->id;
                $order->total_price = 0;
                $order->invoice_number = 'INV-'.Auth::user()->id.date('Ymdhis');
                $order->courier_service = $courier.'-'.$service;
                $order->status = 'SUBMIT';
                if($order->save()){
                    $total_price = 0;
                    $total_weight = 0;
                    //looping pada carts
                    foreach($carts as $cart){
                        $id = (int) $cart['id'];
                        $quantity = (int)$cart['quantity'];
                        $book = Book::find($id);
                        //periksa apakah bukunya ada
                        if($book){
                            //jika ada, periksa lagi apakah stoknya melebihi jumlah yang ingin dibeli
                            if($book->stock >= $quantity){
                                $total_price += $book->price * $quantity;
                                $total_weight += $book->weight * $quantity;

                                //buat BookOrder
                                $book_order = new BookOrder();
                                $book_order->book_id = $book->id;
                                $book_order->order_id = $order->id;
                                $book_order->quantity = $quantity;
                                //jika book_order berhasil disimpan
                                if($book_order->save()){
                                    //kurangi stok
                                    $book->stock -= $quantity;
                                    //kemudian simpan
                                    $book->save();
                                }
                            }
                            else{
                                $error++;
                                throw new \Exception('Stok Habis');
                            }
                        }
                        else{
                            $error++;
                            throw new \Exception('Buku tidak ditemukan');
                        }
                    }
                    $totalBill = 0;
                    $weight = $total_weight * 1000; //ubah ke gram
                    if($weight <= 0){
                        $error++;
                        throw new \Exception('Weight null');
                    }
                    $data =[
                        "origin" => $origin,
                        "destination" => $destination,
                        "weight" => $weight,
                        "courier" => $courier
                    ];
                    $data_cost = $this->getServices($data);
                    if($data_cost['error']){
                        $error++;
                        throw new \Exception('Layanan tidak tersedia');
                    }

                    $response = json_decode($data_cost['response']);
                    $costs = $response->rajaongkir->results[0]->costs;
                    $service_cost = 0;
                    foreach ($costs as $cost){
                        $service_name = $cost->service;
                        if($service == $service_name){
                            $service_cost = $cost->cost[0]->value;
                            break;
                        }
                    }
                    if($service_cost <=0){
                        $error++;
                        throw new \Exception('Service cost invalid');
                    }
                    $total_bill = $total_price + $service_cost;
                    //update total bill order
                    $order->total_price = $total_bill;
                    //lakukan proses penyimpanan. Jika penyimpanan berhasil,
                    //lakukan proses commit ke database dan tampilkan pesan
                    if($order->save()){
                        if($error == 0)
                        {
                            DB::commit();
                            $status = "success";
                            $message = "Transaksi berhasil";

                            /*
                            Konfigurasi Midtrans
                            */
                            \Midtrans\Config::$serverKey = 'SB-Mid-server-MoNwe_nuTguvkqXnR1GCTDFH';
                            \Midtrans\Config::$isProduction = false;
                            \Midtrans\Config::$isSanitized = true;
                            \Midtrans\Config::$is3ds = true;
                            // $data = [
                            //     'order_id' => $order->id,
                            //     'total_price' => $total_bill,
                            //     'invoice_number' => $order->invoice_number
                            // ];
                            $transaction_data = [
                                'transaction_details' => [
                                    'order_id' => $order->invoice_number,
                                    'gross_amount' => $total_bill
                                ]
                            ];
                            //redirect ke halaman pembayaran midtrans
                            $payment_link = \Midtrans\Snap::createTransaction($transaction_data)->redirect_url;
                            $data = [
                                'payment_link' => $payment_link
                            ];
                        }
                        else{
                            $message = "Terdapat ".$error." error";
                        }
                    }
                }
            }
            catch(\Exception $e)
            {
                $message = $e->getMessage();
                DB::rollback();
            }

        }
        else{
            $message = "User tidak ditemukan";
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], 200);
    }

}
