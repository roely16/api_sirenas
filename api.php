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
                                BACKEND_ACTIVATION,
                                NVL(ENABLE, 'N') AS SIRENA_ACTIVA
                            FROM CAT_CORREDOR_SIRENA";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $corredores = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

                    $id_corredor = $data['ID'];

                    $data["expand"] = false; 
                    $data['loading'] = false;
                    $data['enable'] = $data['SIRENA_ACTIVA'] == 'S' ? true : false;

                    // Obtener las sirenas de cada corredor
                    $query = "  SELECT
                                    ID,
                                    NOMBRE,
                                    DIRECCION,
                                    IP,
                                    GATEWAY,
                                    PUERTO,
                                    ID_CORREDOR,
                                    RELAY,
                                    NVL(ENABLE, 'N') AS SIRENA_ACTIVA
                                FROM CAT_SIRENAS
                                WHERE ID_CORREDOR = '$id_corredor'";

                    $stid_ = oci_parse($this->dbConn, $query);
                    oci_execute($stid_);

                    $sirenas = [];

                    while ($sirena = oci_fetch_array($stid_, OCI_ASSOC)) {
                    
                        // Estado de la sirena
                        $sirena['estado'] = [
                            'color' => 'green',
                            'text' => 'En Línea'
                        ];

                        $sirena['expand'] = false;
                        $sirena['loading'] = false;
                        $sirena['sending'] = false;
                        $sirena['enable'] = $sirena['SIRENA_ACTIVA'] == 'S' ? true : false;

                        // Información necesario para realizar la conexión con la sirena
                        $puerto = array_key_exists('PUERTO', $sirena) ? ':' . $sirena['PUERTO'] : null;
                        $sirena['url'] = 'http://' . $sirena['IP'] . $puerto;

                        $sirena['acciones'] = [
                            'encender' => [
                                'url' => $sirena['url'],
                                'body' => [
                                    'submit' => array_key_exists('ENCENDER', $data) ? $data['ENCENDER'] : null
                                ]
                            ],
                            'intermitente' => [
                                'url' => $sirena['url'],
                                'body' => [
                                    'submit' => array_key_exists('INTERMITENTE', $data) ? $data['INTERMITENTE'] : null
                                ]
                            ],
                            'apagar' => [
                                'url' => $sirena['url'],
                                'body' => [
                                    'submit' => array_key_exists('APAGAR', $data) ? $data['APAGAR'] : null
                                ]
                            ]
                        ];

                        $sirenas [] = $sirena;
    
                    }

                    $data['sirenas'] = $sirenas;

                    // Estado del corredor
                    $data['estado'] = [
                        'color' => 'green',
                        'text' => 'En Línea',
                        'result' =>  count($sirenas) . '/' . count($sirenas)
                    ];

                    $corredores [] = $data;

                }

                $this->returnResponse(SUCCESS_RESPONSE, $corredores);
                
            } catch (\Throwable $th) {
                //throw $th;
            }

        }

        public function estado_corredor(){

            try {
                
                $id_corredor = $this->param['id'];
                $estado = $this->param['estado'] ? 'S' : null;

                $query = "UPDATE CAT_CORREDOR_SIRENA SET ENABLE = '$estado' WHERE ID = '$id_corredor'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                // Actualizar 

                $query = "UPDATE CAT_SIRENAS SET ENABLE = '$estado' WHERE ID_CORREDOR = '$id_corredor'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $this->returnResponse(SUCCESS_RESPONSE, $this->param);

            } catch (\Throwable $th) {
                
            }

        }

        public function estado_sirena(){

            try {
                
                $id = $this->param['id'];
                $estado = $this->param['estado'] ? 'S' : null;

                $query = "UPDATE CAT_SIRENAS SET ENABLE = '$estado' WHERE ID = '$id'";

                $stid = oci_parse($this->dbConn, $query);
                $result = oci_execute($stid);

                $this->returnResponse(SUCCESS_RESPONSE, $result);

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

        public function check_connection(){

            try {
                
                $ip = $this->param["ip"];

                // exec("ping -c 1 $ip", $output, $result);

                $ch = curl_init($ip);
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));

                // Execute
                $output = curl_exec($ch);

                // Check if any error occured
                if(!curl_errno($ch)) {

                    $info = curl_getinfo($ch);
                    $output = 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'];

                }else{

                    $output = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    // $output = curl_getinfo($httpcode);;

                }
                // Close handle
                curl_close($ch);

                $this->returnResponse(SUCCESS_RESPONSE, $output);

            } catch (\Throwable $th) {
                


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