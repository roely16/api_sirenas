<?php
    
    // error_reporting(E_ERROR | E_PARSE);
    
    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    class Api extends Rest{

        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function obtener_corredores(){

            try {
                
                $query = "  SELECT
                                ID,
                                NOMBRE,
                                UBICACION,
                                FUNCION_ACTIVACION,
                                ENCENDER,
                                INTERMITENTE,
                                APAGAR, 
                                PANEL_CONTROL,
                                BACKEND_ACTIVATION
                            FROM CAT_CORREDOR_SIRENA";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $corredores = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                    
                    $data["expand"] = false; 
                    $data["sirenas"] = [];

                    $corredores [] = $data;

                }

                $this->returnResponse(SUCCESS_RESPONSE, $corredores);
                
            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function obtener_sirenas(){

            try {
                
                $id_corredor = $this->param["id_corredor"];

                $query = "  SELECT *
                            FROM CAT_SIRENAS
                            WHERE ID_CORREDOR = '$id_corredor'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $sirenas = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {
                    
                    $sirenas [] = $data;

                }

                $this->returnResponse(SUCCESS_RESPONSE, $sirenas);

            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function corredor1(){

            $accion = $this->param['accion'];
            $corredor = $this->param['corredor'];
            $sirena = $this->param['sirena'];

            $ch = curl_init();

            $url = 'http://' . $sirena['IP'] . ':' . $sirena['PUERTO'] . $accion;

            curl_setopt($ch, CURLOPT_URL, $url); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
            curl_setopt($ch, CURLOPT_HEADER, 0); 

            $data = curl_exec($ch); 

            curl_close($ch); 

            if (!$data) {
                
                $response = [
                    "status" => 100,
                    "message" => "Error de conexión"
                ];

                $this->returnResponse(SUCCESS_RESPONSE, $response);

            }

            $response = [
                "status" => 200,
                "message" => "Conexión exitosa"
            ];

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function corredor2(){

            $accion = $this->param['accion'];
            $corredor = $this->param['corredor'];
            $sirena = $this->param['sirena'];

            $service_port = 2000;

            $exit ="EXIT \r\n";

            $weight = 0;

            $response = false;

            if (!($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {

                // No se pude crear el socket

                $errorcode = socket_last_error();

                $errormsg = socket_strerror($errorcode);
            
                $response = [
                    "status" => 100,
                    "message" => "Couldn't create socket: [{$errorcode}] {$errormsg} \n"
                ];

            }else{
                
                // Se crea el socket 

                socket_set_option($socket,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>5, "usec"=>0));

			    socket_set_option($socket,SOL_SOCKET, SO_SNDTIMEO, array("sec"=>5, "usec"=>0));

                if (!($result = @socket_connect($socket, $sirena['IP'], $service_port))) {

                    $response = [
                        "status" => 100,
                        "message" => "socket_connect() failed."
                    ];
                
                }else{

                    $in = [
                        $sirena["RELAY"] => $accion
                    ];

                    // El socket conecta con la ip
                    socket_write($socket, $in, strlen($in));

                    if (false !== ($bytes = socket_recv($socket, $buf, 30,MSG_PEEK))) {

                        $weight = $buf;

                        socket_write($socket, $exit, strlen($in));

                        $response = [
                            "status" => 200,
                            "message" => "La sirena ha sido activada."
                        ];

                    }else {

                        $response = [
                            "status" => 100,
                            "message" => "socket_recv() failed."
                        ];

                    }

                    socket_close($socket);

                }

            }

            $this->returnResponse(SUCCESS_RESPONSE, $response);
        }

        public function corredor3(){

        }

    }

?>