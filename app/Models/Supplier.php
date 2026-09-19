<?php
namespace App\Models;
class Supplier extends Team4Document
{
    protected $collection = 'suppliers';
    protected $fillable = ['business_id','code','legal_name','tax_id','status','payment_terms','notes'];
}
