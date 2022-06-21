<?php 

    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, "http://172.23.25.138:8888/?status=Encender");

    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // $output contains the output string
    $output = curl_exec($ch);

    // close curl resource to free up system resources
    curl_close($ch);     

// // Further processing ...
// if ($server_output == "OK") { 

//  } else { 
     
//   }

?>