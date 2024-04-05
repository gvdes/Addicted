<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Store;
use App\Models\Provider;
use App\Models\ProductCategory;
use App\Models\UnitMeasure;
use App\Models\Client;
use App\Models\ClientType;
use App\Models\PaymentMethod;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class ResourcesController extends Controller
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

    public function ping(){
        return response()->json(true,200);
    }

    public function matchProvider(){
        $dbpro = Provider::pluck('fs_id')->toArray();


        $accpro = "SELECT CODPRO AS code FROM F_PRO";
        $exec = $this->conn->prepare($accpro);
        $exec->execute();
        $proac = $exec->fetchAll(\PDO::FETCH_ASSOC);

        $proacCodes = array_column($proac, 'code');

        $dife = array_diff($proacCodes,$dbpro);
        $val = array_values($dife);

        $profa = "SELECT CODPRO, NOFPRO, DOMPRO,POBPRO,PROPRO,CPOPRO FROM F_PRO WHERE CODPRO IN (".implode(',',$val).")";
        $exec = $this->conn->prepare($profa);
        $exec->execute();
        $providers = $exec->fetchAll(\PDO::FETCH_ASSOC);
        foreach($providers as $provider){
            $insert[] = [
                "fs_id"=>$provider['CODPRO'],
                "fiscal_name"=>$provider['NOFPRO'],
                "address"=>json_encode([
                    "domicilio"=>$provider['DOMPRO'],
                    "poblacion"=>$provider['POBPRO'],
                    "codigo_postal"=>$provider['CPOPRO'],
                    "delegacion"=>$provider['PROPRO'],
                ]),
                "_type"=>1,
                "_state"=>1
            ];
        }
        $provi = new Provider;
        $provi->insert($insert);
    }

    public function matchCategories(){
        $res = [
            "fails"=>[],
            "goals"=>[]
        ];
        $ins = [];
        $accpro = "SELECT DISTINCT
        F_SEC.DESSEC,
        F_FAM.DESFAM,
        F_ART.CP1ART
        FROM ((F_SEC
        INNER JOIN F_FAM ON F_FAM.SECFAM = F_SEC.CODSEC)
        INNER JOIN F_ART ON F_ART.FAMART = F_FAM.CODFAM)";

        $exec = $this->conn->prepare($accpro);
        $exec->execute();
        $proac = $exec->fetchAll(\PDO::FETCH_ASSOC);

        $categories = ProductCategory::select(
            \DB::raw('GETSECTION(id) AS DESSEC'),
            \DB::raw('GETFAMILY(id) AS DESFAM'),
            \DB::raw('GETCATEGORY(id) AS CP1ART')
        )->get()->toArray();

        $proacUnique = array_map(function($val){ return implode(',',array_map('utf8_encode',$val));}, $proac);
        $categoriesUnique = array_map(function($val){ return implode(',',array_map('utf8_encode',$val));}, $categories);


        $differences = array_diff($proacUnique, $categoriesUnique);
        $differencesAsArrays = array_map(function($val){ return explode(',',$val);}, $differences);

        $faltantes = array_values($differencesAsArrays);
        foreach($faltantes as $faltante){
            $seccion = $faltante[0];
            $familia = $faltante[1];
            $categoria = $faltante[2];


            $esec = ProductCategory::where('name',$seccion)->where('deep',0)->first();
            if($esec){
                $efam = ProductCategory::where('name',$familia)->where('deep',1)->where('root',$esec->id)->first();
                if($efam){
                    $ecat = ProductCategory::where('name',$categoria)->where('deep',2)->where('root',$efam->id)->first();
                    if($ecat){
                        $res['fails'][] = $seccion.' '.$familia.' '.$categoria.' ya existe';
                    }else{
                        $ins [] = [
                            "name"=>$categoria,
                            "deep"=>2,
                            "alias"=>$categoria,
                            "root"=>$efam->id
                        ];
                    }
                }else{
                $fami = "SELECT CODFAM FROM F_FAM WHERE DESFAM = "."'".$familia."'"." AND SECFAM = "."'".$esec->alias."'"  ;
                $exec = $this->conn->prepare($fami);
                $exec->execute();
                $alfam = $exec->fetch(\PDO::FETCH_ASSOC);
                    $ins []=[
                        "name"=>$familia,
                        "deep"=>1,
                        "alias"=>$alfam['CODFAM'],
                        "root"=>$esec->id
                    ];
                }
            }else{
                $seci = "SELECT CODSEC FROM F_SEC WHERE DESSEC = "."'".$seccion."'"  ;
                $exec = $this->conn->prepare($seci);
                $exec->execute();
                $alsec = $exec->fetch(\PDO::FETCH_ASSOC);
                    $ins []=[
                        "name"=>$seccion,
                        "deep"=>0,
                        "alias"=>$alsec['CODSEC'],
                        "root"=>0
                    ];
            }
        }
        $json = array_map(function($val){ return implode(',',array_map('utf8_encode',$val));},$ins);
        $unicos = array_unique($json);

        $insetar  = array_map(function($json){
            return explode(',',$json);
        },$unicos);
        if($insetar){
            $ahogra = [];
            foreach($insetar as $inset){
                $ahogra[] = [
                    "name"=>$inset[0],
                    "deep"=>$inset[1],
                    "alias"=>$inset[2],
                    "root"=>$inset[3]
                ];
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('SET SQL_SAFE_UPDATES=0');
            $caty = new ProductCategory;
            $caty->insert($ahogra);
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SQL_SAFE_UPDATES=1');
            return mb_convert_encoding($ahogra,'UTF-8');
        }else{
            return response()->json('no hay nada por replicar');
        }

    }

    public function matchProducts(){
        $res = [
            "fails"=>[],
            "goals"=>[]
        ];
        $dbpro = Product::pluck('code')->toArray();

        $accpro = "SELECT CODART AS code FROM F_ART";
        $exec = $this->conn->prepare($accpro);
        $exec->execute();
        $proac = $exec->fetchAll(\PDO::FETCH_ASSOC);

        $proacCodes = array_column($proac, 'code');

        $dife = array_diff($proacCodes,$dbpro);

        $profal = array_values($dife);

        $faltantes = array_map([$this,'com'],$profal);

        $proft = "SELECT CCOART, CODART, EANART, DLAART, DEEART, REFART, UPPART, PCOART, PHAART, FAMART, CP1ART, FTEART, UMEART, DSCART, CP3ART FROM F_ART WHERE CODART IN (".implode(',',$faltantes).")";
        $exec = $this->conn->prepare($proft);
        $exec->execute();
        $products = $exec->fetchAll(\PDO::FETCH_ASSOC);

        $conenie = array_map(function($val){ return array_map('utf8_encode',$val);},$products);
        // return $conenie;
        $prod = new Product;
        foreach($conenie as $product){
            $provider = Provider::where('fs_id',$product['PHAART'])->first();
            if($provider){
                $caty = ProductCategory::where('alias', $product['CP1ART'])
                ->whereHas('parent', function ($query) use ($product) {
                    $query->where('alias', $product['FAMART']);
                })
                ->value('id');
                if($caty){
                    $assortment = UnitMeasure::where('name',trim($product['CP3ART']))->value('id');
                    $inspro = [
                        "short_code"=>$product['CCOART'],
                        "code"=>$product['CODART'],
                        "barcode"=>$product['EANART'] === "" ? null : $product['EANART'] ,
                        "description"=>$product['DLAART'],
                        "label"=>$product['DEEART'],
                        "reference"=>$product['REFART'],
                        "pieces"=>$product['UPPART'],
                        "cost"=>$product['PCOART'],
                        "default_amount"=>1,
                        "_provider"=>$provider->id,//provedor
                        "_category"=>$caty,//categoria
                        "_maker"=>intval($product['FTEART']),//fabricante
                        "_unit_mesure"=>intval($product['UMEART']),//unidad medida
                        "_state"=>$product['DSCART'] == 0 ? 1 : 2,
                        "_assortment_unit"=>$assortment,
                    ];
                    try{
                        $prod->insert($inspro);
                    }catch(QueryException $e){     if ($e->getCode() == 23000) {
                        $res['fails'][]= $e->getMessage();
                    } else {
                        throw $e;
                    }}

                    $res['goals'][]=$inspro;
                }else{
                    $res['fails'][]= 'No existe la categoria '.$product['CP1ART'];
                }
            }else{
                $res['fails'][]= 'No existe el proveedor '.$product['PHAART'];
            }
        }
        return response()->json( mb_convert_encoding($res, 'UTF-8'));
    }

    public function matchClient(){
        $dbpro = Client::pluck('fs_id')->toArray();


        $accpro = "SELECT CODCLI AS code FROM F_CLI";
        $exec = $this->conn->prepare($accpro);
        $exec->execute();
        $proac = $exec->fetchAll(\PDO::FETCH_ASSOC);

        $proacCodes = array_column($proac, 'code');

        $dife = array_diff($proacCodes,$dbpro);
        $val = array_values($dife);

        $profa = "SELECT CODCLI, NOFCLI, DOMCLI, POBCLI, CPOCLI, PROCLI, TELCLI, FALCLI, IDETFI, FPACLI, TARCLI, TCLCLI, NVCCLI FROM F_CLI LEFT JOIN T_TFI ON T_TFI.CLITFI = F_CLI.CODCLI WHERE CODCLI IN (".implode(',',$val).") ORDER BY F_CLI.CODCLI ASC";
        $exec = $this->conn->prepare($profa);
        $exec->execute();
        $clients = $exec->fetchAll(\PDO::FETCH_ASSOC);

        foreach($clients as $client){
            $payment = PaymentMethod::where('alias',$client['FPACLI'])->value('id');
            $type = ClientType::where('alias',$client['TCLCLI'])->value('id');
            $phone = $client['TELCLI'] == null ? null : $client['TELCLI'];
            $state = $client['NVCCLI'] == 0 ? 1 : 2;
            $ins[]=[
                "fs_id"=>$client['CODCLI'],
                "name"=>utf8_encode($client['NOFCLI']),
                "address"=>utf8_encode($client['DOMCLI']." COL. ".$client['POBCLI']." C.P. ".$client['CPOCLI']." DEL. ".$client['PROCLI']),
                "celphone"=>$phone,
                "phone"=>null,
                "RFC"=>null,
                "barcode"=>$client['IDETFI'],
                "_payment"=>$payment,
                "_rate"=>intval($client['TARCLI']),
                "_type"=>$type,
                "_state"=>$state,
            ];
        }
        $inscli = new Client;
        $inscli->insert($ins);

        return mb_convert_encoding($ins,'UTF-8');

    }

    private function com($valor){
        return "'".$valor."'";
    }
}
