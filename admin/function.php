<?php

    function getLatest($select,$table,$order,$limit = 5){
        global $con;
        $getstat = $con->prepare("SELECT $select FROM " . DB_PREFIX . "$table  ORDER BY $order DESC LIMIT $limit");
        $getstat->execute();
        $rows = $getstat->fetchAll();
        return $rows;
        
     }  
     
     function sanitize_output($buffer) {

    preg_match_all('#\<textarea.*\>.*\<\/textarea\>#Uis', $buffer, $foundTxt);
    preg_match_all('#\<pre.*\>.*\<\/pre\>#Uis', $buffer, $foundPre);

    // replacing both with <textarea>$index</textarea> / <pre>$index</pre>
    $buffer = str_replace($foundTxt[0], array_map(function($el){ return '<textarea>'.$el.'</textarea>'; }, array_keys($foundTxt[0])), $buffer);
    $buffer = str_replace($foundPre[0], array_map(function($el){ return '<pre>'.$el.'</pre>'; }, array_keys($foundPre[0])), $buffer);


    $search = array(
        '/\>[^\S ]+/s',  
        '/[^\S ]+\</s',
        '/(\s)+/s' ,      
        '/<!--(.|\s)*?-->/' 
    );

    $replace = array(
        '>',
        '<',
        '\\1'
    );

    $buffer = preg_replace($search, $replace, $buffer);

 
    $buffer = str_replace(array_map(function($el){ return '<textarea>'.$el.'</textarea>'; }, array_keys($foundTxt[0])), $foundTxt[0], $buffer);
    $buffer = str_replace(array_map(function($el){ return '<pre>'.$el.'</pre>'; }, array_keys($foundPre[0])), $foundPre[0], $buffer);

    return $buffer;
    }
