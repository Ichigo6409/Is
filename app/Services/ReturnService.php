<?php
namespace App\Services;

use App\Models\{SupplierReturn,CustomerReturn};
use Illuminate\Support\Str;

class ReturnService
{
    public function supplier(array $data)
    {
        $folio='DEV-PROV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        $return=SupplierReturn::create([
            'business_id'=>$data['business_id'],'folio'=>$folio,'supplier_id'=>$data['supplier_id']??null,
            'status'=>'RECEIVED','reason'=>$data['reason'],'source_type'=>'MANUAL',
            'source_reference'=>$data['reference']??null,'created_by'=>$data['actor_id']??'SYSTEM'
        ]);
        return $return;
    }

    public function customer(array $data)
    {
        $folio='DEV-CLI-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        $return=CustomerReturn::create([
            'business_id'=>$data['business_id'],'folio'=>$folio,'customer_id'=>$data['customer_id']??null,
            'customer_type'=>$data['customer_type']??'ALUMNO','status'=>'RECEIVED',
            'reason'=>$data['reason'],'sale_reference'=>$data['reference']??null,
            'resolution'=>$data['resolution'],'created_by'=>$data['actor_id']??'SYSTEM'
        ]);
        return $return;
    }
}
