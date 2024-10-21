<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Stopwatch;
use Illuminate\Support\Facades\DB;




class StocksController extends Controller
{
    public function __construct(){
        $rout = env("ACCESS");
        $ipserv = getHostByName(getHostName());
        $this->wrk = Store::where('local_domain',$ipserv)->first();
        $this->access = $rout.'\\'.$this->wrk->access_file.'.accdb';//conexion a access de sucursal
        if(file_exists($this->access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$this->access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die($this->access." no es un origen de datos valido."); }
    }

    public function replyStock(){
        $actuwar = [];
        $actualizados =[];
        $workpoint = $this->wrk->id;
        $warehouses = Warehouse::where('_store',$workpoint)->get();
        foreach($warehouses as $warehouse){
            $inicio = microtime(true);
            echo 'actualizando '.$warehouse->alias." \n";
            try{
                $sto  = "SELECT ARTSTO, ALMSTO,  CLng(ACTSTO) AS ACT FROM F_STO WHERE ALMSTO = "."'".$warehouse->alias."'";
                $exec = $this->conn->prepare($sto);
                $exec -> execute();
                $stocks=$exec->fetchall(\PDO::FETCH_ASSOC);
            }catch (\PDOException $e){ die($e->getMessage());}

            $stodb = ProductStock::join('products', 'products.id', '=', 'product_stock._product')
            ->join('warehouses', 'warehouses.id', '=', 'product_stock._warehouse')
            ->where('warehouses.id', '=', $warehouse->id)
            ->select('products.code AS ARTSTO', 'warehouses.alias AS ALMSTO', 'product_stock._current AS ACT','product_stock.reserved AS DIS')
            ->get()->toArray();

            $stodbcode = array_column($stodb, 'ARTSTO');

            $filteredStocks = array_filter($stocks, function($stock) use ($stodbcode) {
                return in_array($stock['ARTSTO'],$stodbcode);
            });
            $indb =  array_values($filteredStocks);

            $texdb = array_map(function($val){ unset($val['DIS']); return implode(',',array_map('utf8_encode', $val ));},$stodb);
            $textacc = array_map(function($val){ return implode(',',array_map('utf8_encode',$val));},$indb);
            $dif = array_diff($textacc,$texdb);
            $arregloact = array_map(function($val){ return explode(',',$val);},$dif);
            $act = array_values($arregloact);
            $actualizados = [];

            $updates = array_map(function($val) use ($warehouse) {
                $product = Product::where('code',$val[0])->value('id');
                $available = ProductStock::where('_warehouse',$warehouse->id)->where('_product',$product)->value('reserved');
                        $res = [
                            "_warehouse"=>$warehouse->id,
                            "_product"=>$product,
                            "_current"=>$val[2],
                            "available"=>$val[2] - $available,
                            "_state"=>2,
                            "_min"=>0,
                            "_max"=>0,
                            "reserved"=>0,
                            "in_coming"=>0
                        ];
                        return $res;
            },$act);
            $sinnull = array_filter($updates, function($val){
                return $val['_product'] !== null;
            });
            $upd = array_values($sinnull);
            $chunks = array_chunk($upd, 500); // Dividir en lotes de 500 registros
            $act = [];
            foreach ($chunks as $chunk) {

                $updst = ProductStock::upsert($chunk,['_warehouse','_product'],['_current','available']);
                $act[] = $updst;
            }

            $actualizados = count($act);
            // $updst = ProductStock::upsert($upd,['_warehouse','_product'],['_current','available']);
            // $actualizados = $updst / 2;
            // $actuwar[] = ['warehouse'=>[$warehouse->alias=>count($actualizados)]];
            $termino = microtime(true);
            echo  'warehouse '.' => '.$warehouse->alias.' => '.$actualizados.' tiempo :'.round($termino-$inicio,2)." seg."." \n";
            }
        echo 'fin de actualizaciones :)'." \n";
        // return $texdb;
    }
}
