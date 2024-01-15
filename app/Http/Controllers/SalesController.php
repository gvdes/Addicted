<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Sale;
use App\Models\Client;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\PaymentMethod;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\SaleBodie;
use App\Models\SaleCollectionLine;





class SalesController extends Controller
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

    public function replySales(){
        $workpoint = $this->wrk->id;
        $date = now()->format("Y-m-d");
        $sday = Sale::whereDate('created_at',$date)->where('_store',$workpoint)->get();

        if(count($sday) == 0){
            $sfsday = "SELECT TIPFAC&'-'&CODFAC AS TICKET, CLIFAC AS CLIENTE, CNOFAC AS NOMCLI, DEPFAC AS USUARIO, ALMFAC AS ALMACEN, TOTFAC AS TOTAL, FOPFAC AS FORMAP, FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS CREACION, TERFAC AS TERMINAL FROM F_FAC WHERE FECFAC =DATE()";
        }else{
            foreach($sday as $sale){
                $fact[]="'".$sale->num_ticket."'";
            }
            $sfsday = "SELECT TIPFAC&'-'&CODFAC AS TICKET, CLIFAC AS CLIENTE, CNOFAC AS NOMCLI, DEPFAC AS USUARIO, ALMFAC AS ALMACEN, TOTFAC AS TOTAL, FOPFAC AS FORMAP, FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS CREACION, TERFAC AS TERMINAL FROM F_FAC WHERE FECFAC =DATE() AND  TIPFAC&'-'&CODFAC NOT IN (".implode(",",$fact).")";
        }

        $exec = $this->conn->prepare($sfsday);
        $exec -> execute();
        $fact=$exec->fetchall(\PDO::FETCH_ASSOC);
        if($fact){
            foreach($fact as $fs){
                $ptick[] = "'".$fs['TICKET']."'";
                $client = Client::where('fs_id',$fs['CLIENTE'])->value('id');
                $user = User::where('TPV_id',$fs['USUARIO'])->value('id');
                $warehouse = Warehouse::where('alias',$fs['ALMACEN'])->where('_store',$workpoint)->value('id');
                $payment = PaymentMethod::where('alias',$fs['FORMAP'])->value('id');
                $cash = CashRegister::where('terminal',$fs['TERMINAL'])->where('_store',$workpoint)->value('id');
                $facturas []  = [
                    "num_ticket"=>$fs['TICKET'],
                    "_client"=>$client,
                    "name"=>$fs['NOMCLI'],
                    "_user"=>$user,
                    "_store"=>intval($workpoint),
                    "_warehouse"=>$warehouse,
                    "total"=>$fs['TOTAL'],
                    "_payment"=>$payment,
                    "created_at"=>$fs['CREACION'],
                    "updated_at"=>now(),//->toDateTimeString(),
                    "_cash"=>$cash
                ];
            }
            $sfac = new Sale;
            $sfac->insert($facturas);
            echo "se insertaron las facturas....";

            $prday = "SELECT TIPLFA&'-'&CODLFA AS TICKET, ARTLFA AS ARTICULO, CANLFA AS CANTIDAD, PRELFA AS PRECIO, TOTLFA AS TOTAL, COSLFA AS COSTO FROM F_LFA WHERE TIPLFA&'-'&CODLFA IN (".implode(",",$ptick).")";
            $exec = $this->conn->prepare($prday);
            $exec -> execute();
            $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($profac){
                foreach($profac as $pro){
                    $sale = Sale::where('num_ticket',$pro['TICKET'])->where('_store',$workpoint)->value('id');
                    $product = Product::where('code',$pro['ARTICULO'])->first();
                    $produ [] = [
                        "_sale"=>$sale,
                        "_product"=>$product->id,
                        "amount"=>$pro['CANTIDAD'],
                        "price"=>$pro['PRECIO'],
                        "total"=>$pro['TOTAL'],
                        "COST"=>$product->cost
                    ];
                }
                $bodie = new SaleBodie;
                $bodie->insert($produ);
                echo "se insertaron las lineas de facturas....";
            }
            $paday = "SELECT TFALCO&'-'&CFALCO AS TICKET, FORMAT(FECLCO,'YYYY-mm-dd')&' '&'00:00:00' AS CREACION, IMPLCO AS TOTAL,CPTLCO AS  CONCEPTO, FPALCO AS FAP, TERLCO AS TERMINAL FROM F_LCO WHERE TFALCO&'-'&CFALCO IN (".implode(",",$ptick).")";
            $exec = $this->conn->prepare($paday);
            $exec -> execute();
            $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($payfac){
                $colsTab = array_keys($payfac[0]);//llaves de el arreglo
                foreach($payfac as $paym){
                    foreach($colsTab as $col){ $paym[$col] = utf8_encode($paym[$col]); }
                    $salep = Sale::where('num_ticket',$paym['TICKET'])->where('_store',$workpoint)->value('id');
                    $cashs = CashRegister::where('terminal',$fs['TERMINAL'])->where('_store',$workpoint)->value('id');
                    $payment = PaymentMethod::where('alias',$paym['FAP'])->value('id');
                    $pagos[] = [
                        "_sale"=>$salep,
                        "created_at"=>$paym['CREACION'],
                        "total"=>$paym['TOTAL'],
                        "concept"=>$paym['CONCEPTO'],
                        "_collection"=>$payment,
                        "_cash"=>$cashs
                    ];
                }
                $collect = new SaleCollectionLine;
                $collect->insert($pagos);
                echo "Se replicaron los pagos de las facturas....";
            }
            echo "Se añadieron ".count($fact)." facturas fin :)";
            return "Se añadieron ".count($fact)." facturas";
        }else{
            echo "No hay facturas que replicar bro";
            return "No hay facturas que replicar bro";
        }

    }

    public function matchSales(){
        $workpoint = $this->wrk->id;
        $date = now()->format("Y-m-d");
        $sday = Sale::whereDate('created_at',$date)->where('_store',$workpoint)->get();

        $sfsday = "SELECT TIPFAC&'-'&CODFAC AS TICKET, CLIFAC AS CLIENTE, CNOFAC AS NOMCLI, DEPFAC AS USUARIO, ALMFAC AS ALMACEN, TOTFAC AS TOTAL, FOPFAC AS FORMAP, FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS CREACION, TERFAC AS TERMINAL FROM F_FAC";

        $exec = $this->conn->prepare($sfsday);
        $exec -> execute();
        $fact=$exec->fetchall(\PDO::FETCH_ASSOC);
        if($fact){
            foreach($fact as $fs){
                $ptick[] = "'".$fs['TICKET']."'";
                $client = Client::where('fs_id',$fs['CLIENTE'])->value('id');
                $user = User::where('TPV_id',$fs['USUARIO'])->value('id');
                $warehouse = Warehouse::where('alias',$fs['ALMACEN'])->where('_store',$workpoint)->value('id');
                $payment = PaymentMethod::where('alias',$fs['FORMAP'])->value('id');
                $cash = CashRegister::where('terminal',$fs['TERMINAL'])->where('_store',$workpoint)->value('id');
                $facturas []  = [
                    "num_ticket"=>$fs['TICKET'],
                    "_client"=>$client,
                    "name"=>$fs['NOMCLI'],
                    "_user"=>$user,
                    "_store"=>intval($workpoint),
                    "_warehouse"=>$warehouse,
                    "total"=>$fs['TOTAL'],
                    "_payment"=>$payment,
                    "created_at"=>$fs['CREACION'],
                    "updated_at"=>now(),//->toDateTimeString(),
                    "_cash"=>$cash
                ];
            }
            $sfac = new Sale;
            $sfac->insert($facturas);
            echo "se insertaron las facturas....";

            $prday = "SELECT TIPLFA&'-'&CODLFA AS TICKET, ARTLFA AS ARTICULO, CANLFA AS CANTIDAD, PRELFA AS PRECIO, TOTLFA AS TOTAL, COSLFA AS COSTO FROM F_LFA WHERE TIPLFA&'-'&CODLFA IN (".implode(",",$ptick).")";
            $exec = $this->conn->prepare($prday);
            $exec -> execute();
            $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($profac){
                foreach($profac as $pro){
                    $sale = Sale::where('num_ticket',$pro['TICKET'])->where('_store',$workpoint)->value('id');
                    $product = Product::where('code',$pro['ARTICULO'])->first();
                    $produ [] = [
                        "_sale"=>$sale,
                        "_product"=>$product->id,
                        "amount"=>$pro['CANTIDAD'],
                        "price"=>$pro['PRECIO'],
                        "total"=>$pro['TOTAL'],
                        "COST"=>$product->cost
                    ];
                }
                $bodie = new SaleBodie;
                $bodie->insert($produ);
                echo "se insertaron las lineas de facturas....";
            }
            $paday = "SELECT TFALCO&'-'&CFALCO AS TICKET, FORMAT(FECLCO,'YYYY-mm-dd')&' '&'00:00:00' AS CREACION, IMPLCO AS TOTAL,CPTLCO AS  CONCEPTO, FPALCO AS FAP, TERLCO AS TERMINAL FROM F_LCO WHERE TFALCO&'-'&CFALCO IN (".implode(",",$ptick).")";
            $exec = $this->conn->prepare($paday);
            $exec -> execute();
            $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($payfac){
                $colsTab = array_keys($payfac[0]);//llaves de el arreglo
                foreach($payfac as $paym){
                    foreach($colsTab as $col){ $paym[$col] = utf8_encode($paym[$col]); }
                    $salep = Sale::where('num_ticket',$paym['TICKET'])->where('_store',$workpoint)->value('id');
                    $cashs = CashRegister::where('terminal',$paym['TERMINAL'])->where('_store',$workpoint)->value('id');
                    $payment = PaymentMethod::where('alias',$paym['FAP'])->value('id');
                    $pagos[] = [
                        "_sale"=>$salep,
                        "created_at"=>$paym['CREACION'],
                        "total"=>$paym['TOTAL'],
                        "concept"=>$paym['CONCEPTO'],
                        "_collection"=>$payment,
                        "_cash"=>$cashs
                    ];
                }
                $collect = new SaleCollectionLine;
                $collect->insert($pagos);
                echo "Se replicaron los pagos de las facturas....";
            }
            echo "Se añadieron ".count($fact)." facturas fin :)";
            return "Se añadieron ".count($fact)." facturas";
        }else{
            echo "No hay facturas que replicar bro";
            return "No hay facturas que replicar bro";
        }
    }
}
