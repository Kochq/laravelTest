<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
function diferenciaHoraReporte(string $time, string $day) : bool {
    [$h,$m,$s] = explode(":", $time);
    [$d,$mo,$y] = explode("/", $day);

    $time = mktime(intval($h+3), intval($m), intval($s), intval($mo), intval($d), intval($y));
    $timeDifference = (time() - $time) / 60; // Diferencia en minutos
    return $timeDifference > 60; // Paso mas de una hora sin reportar
}

class TxtController extends Controller {
    public function parsearCompleto($carpeta) {
        $generalTxt = file("http://relevar.com.ar/datos/riego/$carpeta/reportes/general.txt");
        //$generalTxt = file("../generalTxt.txt");

        $fullObj = (object) array();

        foreach($generalTxt as &$data) {
            $data = explode(",", $data);
            if(!isset($data[1])) {
                continue;
            }

            [$id, $lat] = explode(":", $data[0]);
            $id = "eq".$id;

            $obj = (object) array();

            $obj->lat = $lat;
            $obj->lng = $data[2];
            $obj->direction = $data[4];
            $obj->rumbo = $data[5];
            $obj->day = $data[6];
            $obj->time = $data[7];
            $obj->regando = true ? $data[8] == "00" : false;
            $obj->porcentajeAvance = explode(".", $data[10])[2];
            $obj->direccion2 = $data[12]; // FW, RV, UT
            $obj->presion = $data[15];
            $obj->tension = $data[16];
            $obj->horimetro = $data[20];

            $fullObj->$id = $obj;
        }

        echo json_encode($fullObj);
    }

    public function parsearGraficos($carpeta) {
        $generalTxt = file("http://relevar.com.ar/datos/riego/$carpeta/reportes/general.txt");

        $estaRegando = 0;
        $noEstaRegando = 0;
        $estaReportando = 0;
        $noEstaReportando = 0;
        $estaEncendido = 0;
        $noEstaEncendido = 0;
        foreach($generalTxt as &$data) {
            $data = explode(",", $data);
            if(!isset($data[1])) {
                continue;
            }

            $regando = ($data[8] == "00");
            if($regando) {
                $estaRegando++;
            } else {
                $noEstaRegando++;
            }

            $ultimoReporteHora = $data[7];
            $ultimoReporteDia = $data[6];
            if(diferenciaHoraReporte($ultimoReporteHora, $ultimoReporteDia)) {
                $noEstaReportando++;
            } else {
                $estaReportando++;
            }

            $direction = $data[4];
            if($direction == "--" && !$regando) {
                $noEstaEncendido++;
            } else {
                $estaEncendido++;
            }
        }

        $fullObj = (object) array();
        $fullObj->estaReportando = $estaReportando;
        $fullObj->noEstaReportando = $noEstaReportando;
        $fullObj->estaRegando = $estaRegando;
        $fullObj->noEstaRegando = $noEstaRegando;
        $fullObj->estaEncendido = $estaEncendido;
        $fullObj->noEstaEncendido = $noEstaEncendido;

        echo json_encode($fullObj);
    }

    public function parsearPedido(string $carpeta, string $pedido) {
        $generalTxt = file("http://relevar.com.ar/datos/riego/$carpeta/reportes/general.txt");
        $nombresTxt = file("http://relevar.com.ar/datos/riego/$carpeta/NROS_SMS.txt");
        //$generalTxt = file("../generalTxt.txt");
        $nombres = array();


        foreach($nombresTxt as &$line) {
            $nombres[] = explode(",", $line)[0];
        }

        $fullObj = array();

        foreach($generalTxt as &$data) {
            $data = explode(",", $data);
            if(!isset($data[1])) {
                continue;
            }

            [$id, $lat] = explode(":", $data[0]);

            $obj = (object) array();

            $obj->id = $id;
            $obj->nombreEq = $nombres[$id-1];
            $obj->lat = $lat;
            $obj->lng = $data[2];
            $obj->direction = $data[4];
            $obj->rumbo = $data[5];
            $obj->day = $data[6];
            $obj->time = $data[7];
            $obj->regando = $data[8] == "00";
            $obj->porcentajeAvance = explode(".", $data[10])[2];
            $obj->direccion2 = $data[12]; // FW, RV, UT
            $obj->presion = $data[15];
            $obj->tension = $data[16];
            $obj->horimetro = $data[20];


            switch($pedido) {
                case "regando": {
                    if($obj->regando) {
                        $obj->stat = true;
                    } else {
                        $obj->stat = false;
                    }
                    break;
                }
                case "reportando": {
                    if(diferenciaHoraReporte($obj->time, $obj->day)) {
                        $obj->stat = false;
                    } else {
                        $obj->stat = true;
                    }
                    break;
                }
                case "encendidos": {
                    if($obj->direction == "--" && !$obj->regando) {
                        $obj->stat = false;
                    } else {
                        $obj->stat = true;
                    }
                    break;
                }
            }
            $fullObj[] = $obj;
        }

        echo json_encode($fullObj);
    }
}

