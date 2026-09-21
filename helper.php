<?
if(!function_exists("base64UrlEncode")):
    function base64UrlEncode($text) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }
endif;

if(!function_exists("redirect_to")):
    function redirect_to($url) {
        header("Location: " . $url);
        exit;
    }
endif;

if(!function_exists("createSlug")):
    function createSlug($name, $mdl, $separator = "-", $custom_table = 0, $max_slug_length  = null) {
        $base_length = $max_slug_length;
        if(isset($max_slug_length)):
            $suffix_reserve = 15;
            $base_length = max(1, $max_slug_length - $suffix_reserve);
        endif;
        
        $orig_slug = $slug = generate_slug($name, $base_length, $separator);
        $max_retry = 100; $retry = 0;
        
        while($retry <= $max_retry):
            //$obj = $slug == DEFAULT_SLUG ? [["slug" => DEFAULT_SLUG]]: $mdl->get(["slug" => $slug]);
        
            if($custom_table == 1):
                $obj = $mdl->get_labels(["slug" => $slug]);
            else:
                $obj = $mdl->get(["slug" => $slug]);
            endif;
        
            if(!$obj): break; endif;
            
            $arr = explode($separator, $obj[0]["slug"]);
            $last_val = end($arr);
            $next_val = is_numeric($last_val) ? $last_val + 1 : 1;
            $retry++;
            $slug = $orig_slug.$separator.$next_val;
        endwhile;
        
        $slug = $retry > $max_retry ? $slug."-".time() : $slug;
        return $slug;
    }
endif;

if(!function_exists("create_list_name")):
function create_list_name($name, $mdl, $auto_id, $separator = " ") {
    $orig_slug = $slug = $name;
    $max_retry = 100; $retry = 0;
    
    while($retry <= $max_retry):
        $obj = $mdl->get(["name" => $slug, "auto_id" => $auto_id]);
        if(!$obj): break; endif;
        
        $arr = explode($separator, $obj[0]["name"]);
        $last_val = end($arr);
        $next_val = is_numeric($last_val) ? $last_val + 1 : 1;
        $retry++;
        $slug = $orig_slug.$separator.$next_val;
    endwhile;
    
    $slug = $retry > $max_retry ? $slug.$separator.time() : $slug;
    return $slug;
}
endif;

if(!function_exists("generate_slug")):
    function generate_slug($text, $length = null, $separator) {
        $replacements = [
            '<' => '', '>' => '', '-' => ' ', '&' => '', '"' => '', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Æ' => 'Ae', 'Ç' => 'C', "'" => '', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D', 'Ð' => 'D', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ę' => 'E', 'Ě' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G', 'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ĳ' => 'IJ', 'Ĵ' => 'J', 'Ķ' => 'K', 'Ł' => 'L', 'Ľ' => 'L', 'Ĺ' => 'L', 'Ļ' => 'L', 'Ŀ' => 'L', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N', 'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ö' => 'Oe', 'Ø' => 'O', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O', 'Œ' => 'OE', 'Ŕ' => 'R', 'Ř' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ŝ' => 'S', 'Ș' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T', 'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ū' => 'U', 'Ü' => 'Ue', 'Ů' => 'U', 'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U', 'Ŵ' => 'W', 'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z', 'Ż' => 'Z', 'Þ' => 'T', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae', 'ä' => 'ae', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a', 'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ƒ' => 'f', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j', 'ķ' => 'k', 'ĸ' => 'k', 'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l', 'ŀ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n', 'ŋ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe', 'ö' => 'oe', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o', 'œ' => 'oe', 'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'š' => 's', 'ś' => 's', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'ue', 'ū' => 'u', 'ü' => 'ue', 'ů' => 'u', 'ű' => 'u', 'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y', 'ž' => 'z', 'ż' => 'z', 'ź' => 'z', 'þ' => 't', 'α' => 'a', 'ß' => 'ss', 'ẞ' => 'b', 'ſ' => 'ss', 'ый' => 'iy', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', '.' => '-', '€' => '-eur-', '$' => '-usd-'
        ];
        $text = strtr($text, $replacements);
        $text = preg_replace('~[^\pL\d.]+~u', $separator, $text);
        $text = preg_replace('~[^-\w.]+~', $separator, $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', $separator, $text);
        $text = strtolower($text);
        
        if(isset($length) && $length < strlen($text)):
            $text = rtrim(substr($text, 0, $length), '-');
        endif;
        
        return $text;
    }
endif;

if (!function_exists("remove_invisible_characters")):
    function remove_invisible_characters($str, $url_encoded = true) {
        $non_displayables = [];
        if ($url_encoded):
            $non_displayables[] = '/%0[0-8bcef]/i';
            $non_displayables[] = '/%1[0-9a-f]/i';
            $non_displayables[] = '/%7f/i';
        endif;        
        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';
        
        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        }
        while ($count);
        return $str;
    }
endif;

if(!function_exists("generate_random_string")):
    function generate_random_string($length = 10) {
        return substr(str_shuffle(str_repeat($x = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", ceil($length/strlen($x)) )),1,$length);
    }
endif;


/*if(!function_exists("loginLink")):
    function loginLink($token, $continue = "") {
        $token_data = [
            "h" => $token,
            "e" => strtotime("+1 month")
        ];
        $hash = encryptString(jsonEncode($token_data));
        $url = SITE_URL."login/".$hash."?continue=".urlencode($continue);        
        return $url;
    }
endif;

if(!function_exists("public_link")):
    function public_link($token, $segmant, $continue = "") {
        $token_data = [
            "h" => $token,
            "e" => strtotime("+1 month"),
            "q" => $continue,
        ];
        $hash = encryptString(jsonEncode($token_data));
        $url = PUBLIC_URL."$segmant/".$hash;        
        return $url;
    }
endif;*/

if(!function_exists("otpLink")):
    function otpLink($hash, $continue = "") {
        $url = SITE_URL."login/".$hash."?continue=".urlencode($continue);
        return $url;
    }
endif;

if(!function_exists("loginLink")):
    function loginLink($auto_id, $secret, $continue = "") {
        $token_data = [
            "u" => $auto_id,
            "s" => $secret,
            "e" => strtotime("+1 month")
        ];
        $hash = encryptString(jsonEncode($token_data));
        $url = SITE_URL."login/".$hash."?continue=".urlencode($continue);
        return $url;
    }
endif;

if(!function_exists("public_link")):
    function public_link($auto_id, $secret, $segmant, $continue = "") {
        $token_data = [
            "u" => $auto_id,
            "s" => $secret,
            "e" => strtotime("+1 month"),
            "q" => $continue,
        ];
        $hash = encryptString(jsonEncode($token_data));
        $url = PUBLIC_URL."$segmant/".$hash;
        return $url;
    }
endif;

if(!function_exists("query_string")):
    function query_string($params = []) {
        $params = array_filter($params, fn($v) => $v !== null && $v !== "");
        return $params ? "?".http_build_query($params, "", "&", PHP_QUERY_RFC3986) : "";     
    }
endif;

if(!function_exists("jsonEncode")):
    function jsonEncode($data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if($json === false):
            $error = json_last_error_msg();
            return json_encode(["status" => 500, "msg" => "Response encoding failed - $error"], JSON_UNESCAPED_UNICODE);
        endif;        
        return $json;
    }
endif;

if(!function_exists("encryptString")):
    function encryptString($string) {        
        $cipher = "AES-128-CTR"; 
        $iv = substr(openssl_random_pseudo_bytes(16), 0, 4); 
        $ciphertext = openssl_encrypt($string, $cipher, ENCRYPTION_KEY, 0, $iv); 
        return rtrim(strtr(base64_encode($iv . $ciphertext), '+/', '-_'), '=');
    }
endif;

if(!function_exists("decryptString")):
    function decryptString($ciphertext_base64) {
        $cipher = "AES-128-CTR"; 
        $ciphertext = base64_decode(strtr($ciphertext_base64, '-_', '+/')); 
        $iv = substr($ciphertext, 0, 4); 
        $ciphertext_raw = substr($ciphertext, 4); 
        return openssl_decrypt($ciphertext_raw, $cipher, ENCRYPTION_KEY, 0, $iv); 
    }
endif;

if(!function_exists("cleanFeedIds")):
    function cleanFeedIds($feed_ids) {
        $rem_arr = ["\[", "\]", "[", "]"];
        $feed_ids = str_replace($rem_arr, "", $feed_ids);
        $feeds = explode(",", $feed_ids);
        return $feeds;
    }
endif;

function remove_special_chars($input) {   
    $cleaned = preg_replace('/[^a-zA-Z0-9\s]/', '', $input);
    $underscored = preg_replace('/\s+/', '_', $cleaned);
    return strtolower($underscored);    
}

if(!function_exists("escapeSolrSpecialChars")):
    function escapeSolrSpecialChars($str) {
        $str = str_replace(["\\"], "", $str);
        return $str;
    }
endif;

if(!function_exists("handleSpecialChar")):
    function handleSpecialChar($text, $remove_tags = 0) {
        $text = htmlspecialchars_decode($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = stripslashes($text);
        $text = $remove_tags ? strip_tags($text) : $text;
        return $text;
    }
endif;

function trial_days_left($trial_end, $current = null) {
    if($current === null):
        $current = date("Y-m-d H:i:s", strtotime(DAYLIGHT_SAVING));
    endif;
    
    $start = strtotime($trial_end);
    $now = strtotime($current);
    
    if($start <= $now):
        return 0;
    endif;
    
    $seconds = $start - $now;
    $days_left = $seconds / (60 * 60 * 24);
    
    return ceil($days_left);
}

if(!function_exists("trial_time_left")):
    function trial_time_left($trial_end, $current = null) {        
        if($current === null):
            $current = date("Y-m-d H:i:s", strtotime(DAYLIGHT_SAVING));
        endif;
        
        $end = strtotime($trial_end);
        $now = strtotime($current);
    
        if($end <= $now):
            return ["value" => 0, "label" => ''];
        endif;
    
        $seconds = $end - $now;
        if($seconds >= 3600):
            $hours = ceil($seconds / 3600);
            return [
                "value" => $hours,
                "label" => $hours > 1 ? "hours left" : "hour left"
            ];
        endif;
    
        $mins = ceil($seconds / 60);
        return ["value" => $mins, "label" => $mins > 1 ? "mins left" : "min left"];
    }
endif;

if(!function_exists("days_left")):
    function days_left($end_date, $start_date = "") {
        $start_date = empty($start_date) ? date("Y-m-d") : $start_date;
        $future = strtotime($end_date);
        $timefromdb = strtotime($start_date);
        $timeleft = $future - $timefromdb;
        $daysleft = round((($timeleft/24)/60)/60);
        $daysleft = $daysleft < 1 ? 0 : $daysleft;
        return $daysleft;
    }
endif;

if(!function_exists("sort_author_by_email")):
    function sort_author_by_email($a, $b) {
        return empty($a["email"]);
    }
endif;

if(!function_exists("get_author_name")):
    function get_author_name($name, $gender, $designation) {
        $orig_name = $name;
        $name = str_replace([" and ", ","], [" ", ""], $name);
        $arr = explode(" ", $name);
        $first_name = $orig_name;
        $gender = !empty($gender) ? strtolower($gender) : "";
        $last_name = "";
        
        if(strtolower(trim($designation)) == "hosts"):
            $first_name = !empty($name) ? explode(" ", trim($name))[0] : $name;
        elseif(in_array($gender, ["male", "female"]) && count($arr) > 1):
            //$key = end(array_keys($arr));
            $key = array_key_last($arr);
            $last_name = $arr[$key];
            unset($arr[$key]);
            $first_name = implode(" ", $arr);
        endif;
        
        $names = [
            "first_name"    => ucwords($first_name),
            "last_name"     => ucwords($last_name)
        ];
        return $names;
    }
endif;

function get_location_rank($rec, $locations) {
    $best = 999;    
    foreach($locations as $loc):
        $needCountry = $loc['country_id'] > 0;
        $needState   = $loc['state_id'] > 0;
        $needCity    = $loc['city_id'] > 0;
        
        if($needCountry && $needState && $needCity):
            if($rec['country_id'] == $loc['country_id'] && $rec['state_id']   == $loc['state_id'] && $rec['city_id']    == $loc['city_id']):
                $best = min($best, 1);
            endif;   
            continue; 
        endif;
        
        if($needCountry && $needState && !$needCity):            
            if($rec['country_id'] == $loc['country_id'] && $rec['state_id']   == $loc['state_id']):
                $best = min($best, 2);
            endif;
            continue; 
        endif;
        
        if($needCountry && !$needState && !$needCity):
            if($rec['country_id'] == $loc['country_id']):
                $best = min($best, 3);
            endif;           
            continue;
        endif;
        
    endforeach;    
    return ($best == 999) ? 0 : $best;
}

if(!function_exists("format_author")):
    function format_author($arr, $export, $is_viewed, $feed_name, $is_paid, $filter_location = [], $mp_charts = 0) {        
        $is_viewable = false;
        $filter_co = $filter_st = $filter_loc = $authors = [];
        $total_authors = 0;
        $c2_display_country = $c2_display_state = $c2_display_location = "";
        $delimeter = $export ? "_,_" : "_,_"; //dnt split author by email
        
        if(!empty($filter_location)):
            foreach($filter_location as $loc):
                $filter_loc[] = [
                    "country_id"    => (int)$loc["country_id"],
                    "state_id"      => (int)$loc["state_id"],
                    "city_id"       => (int)$loc["city_id"]
                ];                
            endforeach;            
        endif;
        
        foreach($arr as $row):
            $email_ar = explode($delimeter, $row["e"]);
            
            $loaded_one_email = 0;
            foreach($email_ar as $email_id): 
                $is_viewable = !empty($email_id) ? true : $is_viewable;
                $email = $mp_charts || $is_viewed || $export ? $email_id : (empty($email_id) ? "" : "available in export");
                
                $tw_url = !empty($row["t"]) ? escapeSolrSpecialChars($row["t"]) : "";
                $fb_url = !empty($row["facebook"]) ? escapeSolrSpecialChars($row["facebook"]) : "";
                $in_url = !empty($row["instagram"]) ? escapeSolrSpecialChars($row["instagram"]) : "";
                $ln_url = !empty($row["l"]) ? escapeSolrSpecialChars($row["l"]) : "";
                $name = !empty($row["n"]) ? strtolower($row["n"]) : $feed_name;
                $gender = !empty($row["gender"]) ? strtolower($row["gender"]) : "";
                $designation = !empty($row["d"]) ? $row["d"] : "";                
                //$split_name = $export && empty($row["n"]) ? 0 : 1;
                
                $names = get_author_name($name, $gender, $designation);
                $author_type = isset($row["author_type"]) ? $row["author_type"] : "feed_detail";
                
                if(!empty($designation)):
                    $designation = in_array(strtolower($designation), REPLACE_DESIGNATION) ? "" : $designation;
                endif;
                
                $au_country = isset($row["country"]) ? $row["country"] : "";
                $au_state = isset($row["state"]) ? $row["state"] : "";
                $au_state_code = isset($row["state_code"]) ? $row["state_code"] : "";
                $au_city = isset($row["city"]) ? $row["city"] : "";
                
                $au_location = [];
                $au_location[] = ucwords(strtolower($au_city));
                $au_location[] = !$export && !empty($au_state_code) ? $au_state_code : ucwords(strtolower($au_state));
                $au_location[] = ucwords(strtolower($au_country));
                $author_loc = implode(", ", array_filter($au_location));
                $location_rank = get_location_rank($row, $filter_loc); 
                
                if($location_rank > 0):
                    $c2_display_location = $author_loc;
                endif;
                
                $email = strtolower($email);
                $au_email = !empty($email) ? explode(",", $email) : [];
                
                if($mp_charts):
                    $au_email = array_map(function($email) {
                        return '****@' . explode('@', trim($email), 2)[1];
                    }, $au_email);
                endif;
                
                $author = [
                    "name"                  => ucwords(handleSpecialChar($name)),
                    "first_name"            => handleSpecialChar($names["first_name"]),
                    "last_name"             => handleSpecialChar($names["last_name"]),
                    "email"                 => $au_email,
                    "designation"           => $designation,
                    "phone"                 => !empty($row["phone"]) ? $row["phone"] : "",
                    
                    "linkedin_url"          => $is_paid ? $ln_url : "",
                    "linkedin_handle"       => $is_paid ? linkedinHandle($ln_url) : "",
                    "linkedin_followers"    => 0,
                    "linkedin_viewable"     => !empty($ln_url) ? true : false,
                    
                    "instagram_url"         => $is_paid ? $in_url : "",
                    "instagram_handle"      => $is_paid ? instagramHandle($in_url) : "",
                    "instagram_followers"   => 0,
                    "instagram_viewable"    => !empty($in_url) ? true : false,
                    
                    "twitter_url"           => $is_paid ? $tw_url : "",
                    "twitter_handle"        => $is_paid ? twitterHandle($tw_url) : "",
                    "twitter_followers"     => isset($row["tc"]) ? (int)$row["tc"] : "",
                    "twitter_viewable"      => !empty($tw_url) ? true : false,
                    
                    "facebook_url"          => $is_paid ? $fb_url : "",
                    "facebook_handle"       => $is_paid ? facebookHandle($fb_url) : "",
                    "facebook_followers"    => 0, 
                    "facebook_viewable"     => !empty($fb_url) ? true : false,
                    
                    "gender"                => ucwords($gender),
                    "bio"                   => !empty($row["bio"]) ? $row["bio"] : "",
                    "website"               => !empty($row["pw"]) ? escapeSolrSpecialChars($row["pw"]) : "",
                    "author_type"           => $author_type,
                    "is_viewable"           => !empty($email_id) ? true : false,
                    "country"               => $au_country,
                    "state"                 => $au_state,
                    "city"                  => $au_city,  
                    "location"              => $author_loc,
                    "created_at"            => !empty($row["created_at"]) ? $row["created_at"] : "",
                    "rank"                  => $location_rank
                ];                
                $authors[] = $author;
                $total_authors++;
                
                $loaded_one_email = 1;                
                if($loaded_one_email): //$export && 
                    break;
                endif;
                
            endforeach;
        endforeach;  
        
        $c2_location = $c2_display_location;
        /*if(empty($c2_location) && !empty($c2_display_state)):
            $c2_location = $c2_display_state;
        elseif(empty($c2_location) && !empty($c2_display_country)):
            $c2_location = $c2_display_country;
        endif;*/
        
        usort($authors, "sort_author_by_email");        
        return [
            "authors"           => $authors,
            "is_viewable"       => $is_viewable,
            "total_authors"     => $total_authors,
            "c2_location"       => $c2_location,          
        ];
    }
endif;


function is_valid_email($email) {    
    if(!$email || strlen($email = trim($email)) == 0 or preg_match("/^[\s]+$/",$email)):
        return false;
    endif;
    
    if(preg_match("/^[_+a-zA-Z0-9-]+(\.[_+a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]{1,})*\.([a-zA-Z]{2,}){1}$/",$email)):
        return true;
    endif;
    
    return false;
}

function is_valid_itune_email($itune_email) {
    $is_valid_email = false;
    $itune_email = strtolower(trim($itune_email));
    
    if(!is_valid_email($itune_email)):
        return false;
    endif;
    
    if(!empty($itune_email)):
        $ignore = [
            "noreply@",
            "no-reply@",
            "do-not-reply@",
            "rss@",
            "itunes@",
            "feeds@"
        ];
        $arr = explode('@', $itune_email);
        $prefix = $arr[0]."@";
    
        if(!in_array($prefix, $ignore)):
            $patterns = [
                "/podcast.*\+.*\d/",
                "/^feed\+/",
                "/^feeds\+/",
                "/^rss\+/",
                "/^info\+/",
                "/^\d+@/",
                "/^firstory\.inc\+podcast\+/",
            ];    
            $is_valid_email = true;
            foreach($patterns as $pattern):
                if(preg_match($pattern, $itune_email)):
                    $is_valid_email = false;
                    break;
                endif;
            endforeach;
        endif;
    endif;    
    
    return $is_valid_email;
}

if(!function_exists("append_itune_email")):
    function append_itune_email($authors, $itune) {
        $itune_email = trim(strtolower($itune["email"]));
        $itune_name = $itune["name"];
        $gender = isset($itune["gender"]) ? trim(strtolower($itune["gender"])) : "";
        
        if(empty($itune_email) /*|| !is_valid_itune_email($itune_email)*/):
            return $authors;
        endif;
        
        $exists = 0;
        $authors = !is_array($authors) ? [] : $authors;
        foreach($authors as $author):
            $email_arr = !empty($author["e"]) ? explode(",", $author["e"]) : [];
            $email_arr = array_map('strtolower', $email_arr);
            $email_arr = array_map('trim', $email_arr);
            if(in_array($itune_email, $email_arr)):
                $exists = 1;
                break;
            endif;        
        endforeach;
        
        if(!$exists):
            $authors[] = ["n" => $itune_name, "e" => $itune_email, "author_type" => "itune", "gender" => $gender];            
        endif;
        
        return $authors;                
    }
endif;

if(!function_exists("is_valid_url")):
    function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
endif;

if(!function_exists("trialing_slash")):
    function trialing_slash($url) {
        $url = rtrim($url, "/")."/";
        return $url;
    }
endif;

if(!function_exists("merge_author_by_name")):
    function merge_author_by_name($authors) {
        
        if(empty($authors)):
            return $authors;
        endif;
        
        $merged = $empty_name_authors = [];
        
        foreach($authors as $author):
            $name = trim(strtolower($author["name"]));
            $designation = strtolower(trim($author['designation']));
            
            if(empty($name)):
                $empty_name_authors[] = $author;
                continue;
            endif;
            
            if(!isset($merged[$name])):
                $merged[$name] = $author;
            else:
                $existing_email = isset($merged[$name]["email"]) && is_array($merged[$name]["email"]) ? array_filter($merged[$name]["email"]) : [];
                $new_email = isset($author["email"]) && is_array($author["email"]) ? array_filter($author["email"]) : [];
                $merged[$name]["email"] = array_merge($existing_email, $new_email);
                
                foreach($author as $key => $value):
                    if(!empty($value) && $key != "email"):
                        if(in_array($designation, ["host", "co host"])):
                            $merged[$name][$key] = $value;
                        elseif(!empty(trim($merged[$name]["designation"] ?? ''))):
                            if(empty($merged[$name][$key])):
                                $merged[$name][$key] = $value;
                            endif;
                        elseif(empty($merged[$name][$key])):
                            $merged[$name][$key] = $value;
                        endif;
                    endif;
                endforeach;
            endif;
        endforeach;
        
        $authors = array_merge(array_values($merged), $empty_name_authors);
        return $authors;
    }
endif;

if(!function_exists("get_review_graph_data")):
    function get_review_graph_data($obj) {
        $data = []; $rank = 0;     
        $total_review_count = (int)$obj["total_apple_review_count"];
        if(empty($total_review_count)):
            return $data;
        endif;
        
        $arr = !empty($obj["apple_review_by_country"]) ? jsonDecode($obj["apple_review_by_country"]) : [];
        arsort($arr);
        
        foreach($arr as $code => $count):
            $orig_p = $percentage = ($count / $total_review_count) * 100;
            if($percentage < 1):
                continue;
            endif;
            $percentage = ceil($percentage);
            $code = map_country_code($code);
            
            $data[] = [
                "x"             => ++$rank,
                "short_label"   => strtoupper($code),
                "y"             => $percentage,
                "label"         => country_by_code($code),
            ];
            if($rank >= 10):
                break;
            endif;        
        endforeach;
        
        return $data;
    }
endif;

function isValidUuid(string $uuid): bool {
    return preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid
    ) === 1;
}

function deterministicScramble($text) {
    $chars = str_split($text);
    $length = count($chars);

    if ($length <= 1) {
        return $text;
    }

    // Generate a deterministic seed from the input
    $seed = crc32($text);

    // Fisher-Yates shuffle using deterministic pseudo-random numbers
    for ($i = $length - 1; $i > 0; $i--) {
        $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
        $j = $seed % ($i + 1);

        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return implode('', $chars);
}

function get_dummy_feed($data) {
    $row = [];
    $row["feed_id"] = $data["feed_id"];
    $row["apple_id"] = $data["apple_id"];
    $row["feed_name"] = deterministicScramble($data["feed_name"]);  
    $row["feed_desc"] = "Upgrade to unlock this result.";
    $row["author_name"] = deterministicScramble($data["author_name"]);  
    
    $row["location"] = deterministicScramble($data["location"]);  
    $row["rating_value"] = deterministicScramble($data["rating_value"]);  
    $row["max_review_count"] = deterministicScramble($data["max_review_count"]);
    $row["feed_image_url"] = $data["feed_image_url"];
    
    $row["city"] = "";
    $row["region"] = "";
    $row["country"] = "";

    $row["email_json"] = json_encode([]);
    $row["itune_contact_json"] = json_encode([]);

    $row["guest_names"] = json_encode([]);
    $row["sponsor_names"] = json_encode([]);
    $row["network_name"] = json_encode([]);
    $row["network_id"] = json_encode([]);
    return $row;
}

if(!function_exists("format_feed_data")):
    function format_feed_data($fc, $export = 0, $contact_page = 0, $bright_data = 0, $is_paid = false, $filter = []) {
        $feeds = $fc ? array_column($fc, "feed_id") : [];
        $feed_beats = $audience_type = $total_review = $locations = $loc_data = $lists = $data = $viewed = [];
        $review_graph = $audience_filters = [];
        
        $filter_loc = isset($filter["location"]) ? $filter["location"] : [];
        $offset = isset($filter["offset"]) ? $filter["offset"] : 0;
        $from_list = isset($filter["from_list"]) ? $filter["from_list"] : 0; 
        $mp_charts = isset($filter["mp_charts"]) ? $filter["mp_charts"] : 0; 
        $is_search = isset($filter["is_search"]) ? $filter["is_search"] : 0;
        
        $contact_ct = new \App\Controllers\Contact;  
        $ue_ct = new \App\Controllers\UserEngagement;  
        
        $auto_id = 0;
        if(!$export && !empty($feeds)):  
            $medialist_ct = new \App\Controllers\MediaList;
            $auto_id = $contact_ct->userobj->id;
            
            $lists = $medialist_ct->list_by_feed($feeds); 
            $viewed = !empty($auto_id) ? $contact_ct->get(["feeds" => $feeds, "auto_id" => $auto_id]) : [];
            $viewed = $viewed ? array_column($viewed, null, "feed_id") : [];
        endif;
        
        if($contact_page):
            $audience_ct = new \App\Controllers\Audience;         
            $audience_ids = !empty($fc[0]["audience_type_id_array"]) ? $fc[0]["audience_type_id_array"] : [];
            
            if(!empty($audience_ids)):
                $audience_obj = $audience_ct->get_audience_name($audience_ids);  
                $audience_type = !empty($audience_obj) ? array_column($audience_obj, "name") : [];
                $audience_filters = $audience_ct->format_data($audience_obj, "", 1);
            endif;
        endif;
        
        if($contact_page || $export):        
            $beat_ct = new \App\Controllers\Beats;  
            $beat_feed_mapping = $beat_id_arr = [];
            foreach($fc as $brow):
                $arr = !empty($brow["beats_id"]) ? jsonDecode($brow["beats_id"]) : [];
                $arr = !empty($arr) ? $arr : [];
                $beat_id_arr = array_merge($beat_id_arr, $arr);
                $beat_feed_mapping[$brow["feed_id"]] = $arr;
            endforeach;
            
            if(!empty($beat_id_arr)):
                $beat_id_arr = array_values(array_unique(array_filter($beat_id_arr)));
                $beat_vars = [
                    "ids"       => $beat_id_arr,
                    "columns"   => ["id_auto"],
                    "limit"     => count($beat_id_arr),
                    "offset"    => 0
                ];
                $beats = $beat_ct->list($beat_vars);  
                $feed_beats = !empty($beats["data"]) ? array_column($beats["data"], null, "key") : [];
                $mapping = $beats["mapping"];                
            endif;            
        endif;
        
        if($contact_page && !empty($feeds)):        
            $apple_arr = array_column($fc, "apple_id");
            $chunks = array_chunk($apple_arr, 500);
            foreach($chunks as $chunk):
                $ap_obj = $contact_ct->get_total_reviews_by_apple_id(["apple_id" => $chunk]);
                $total_review = !empty($ap_obj) ? array_merge($total_review, $ap_obj) : $total_review;
            endforeach;
            $total_review = !empty($total_review) ? array_column($total_review, null, "apple_id") : $total_review;
            
            //if($contact_page):
            $apple_id = $apple_arr[0] ?? 0;
            $review_obj = $total_review[$apple_id] ?? [];
            
            if(!empty($review_obj)):
                $review_graph = get_review_graph_data($review_obj);
            endif;
            //endif;
        endif;
        
        $name_prefix = "";
        if($bright_data && isAdmin($auto_id)):
            $name_prefix = "BD:";
        endif;
        
        $counter = 0;
        foreach($fc as $row):
            
            $location = $sponsor = $guests = $networks = $beats = [];
            $counter++;
        
            $feed_id = $row["feed_id"];
            $apple_id = $row["apple_id"];  
            $is_viewed = isset($viewed[$feed_id]) ? true : false;
            $au_item = $row;
            
            //$total_review_count = isset($total_review[$apple_id]) ? $total_review[$apple_id]["total_apple_review_count"] : "";
            //echo "<pre>"; print_r($row); die;
            $total_review_count = isset($row["total_apple_review_count"]) ? $row["total_apple_review_count"] : 0;
            $vector_score = isset($row["vector_score"]) ? $row["vector_score"] : 0;
            $vector_score = rtrim(rtrim(sprintf("%.4f", $vector_score), "0"), ".");
            $doc_score = rtrim(rtrim(sprintf("%.2f", $row["score"]), "0"), ".");
            
            $name_prefix_score = "";
            if(isset($row["vector_score"]) && isAdmin($auto_id) && !$contact_page && !$bright_data):
                $name_prefix_score = "[$vector_score]:";
            elseif(!$contact_page && isAdmin($auto_id) && !$bright_data):
				$name_prefix_score = "[{$doc_score}]:";
            endif;
            
            $row["feed_name"] = $name_prefix_score.$name_prefix.$row["feed_name"];            
            $feed_name = handleSpecialChar($row["feed_name"], 1);
            $arr = !empty($row["email_json"]) ? json_decode($row["email_json"], 1) : [];
            $state_code = isset($row["state_code"]) ? $row["state_code"] : "";
            
            if(isset($row["itune_contact_json"]) && !empty($row["itune_contact_json"])):
                $itune_arr = json_decode($row["itune_contact_json"], 1);
                $arr = append_itune_email($arr, $itune_arr);                
            endif;
            
            $obj = format_author($arr, $export, $is_viewed, $feed_name, $is_paid, $filter_loc, $mp_charts);  
            $authors = !empty($obj["authors"]) ? merge_author_by_name($obj["authors"]) : $obj["authors"];
            
            $auth_name_arr = $authors ? array_column($authors, "name") : [];
            $auth_name_arr = array_values(array_filter(array_unique($auth_name_arr)));
            
            $location[] = isset($row["city"]) ? ucwords(strtolower($row["city"])) : "";
            $location[] = !$export && !empty($state_code) ? $state_code : (isset($row["region"]) ? ucwords(strtolower($row["region"])) : "");
            $location[] = isset($row["country"]) ? ucwords(strtolower($row["country"])) : "";            
            
            $au_item["location"] = implode(", ", array_filter($location));
            if(!$export && !$contact_page && !empty($obj["c2_location"])):
                $au_item["location"] = $obj["c2_location"];                               
            endif;
            
            $blur_node = false;
            /*if(false && $offset == 0 && !$is_viewed && !$is_paid && !$export && in_array($counter, BLUR_NODES)):
                if(!$from_list):
                    $dum_row = $row;            
                    $dum_row["author_name"] = !empty($auth_name_arr) ? handleSpecialChar(implode(", ", array_filter($auth_name_arr))) : "";
                    $dum_row["location"] = $au_item["location"];
                    $row = get_dummy_feed($dum_row);                
                    $feed_name = handleSpecialChar($row["feed_name"], 1);
                    $au_item["location"] = $row["location"];
                    $auth_name_arr = [$row["author_name"]];
                    $blur_node = true;
                elseif($from_list == 2):
                    $feed_id = 0;
                endif;
            endif;*/
            
            $item_no = $offset + $counter;            
            $au_item["idx"] = !$export ? encryptString(jsonEncode(["f" => $feed_id, "i" => $item_no])) : $counter;
            
            $au_item["blur"] = $blur_node;
            $au_item["country"] = empty($row["country"]) ? "" : $row["country"];
            $au_item["region"] = empty($row["region"]) ? "" : $row["region"];
            $au_item["region_code"]  = $state_code;
            $au_item["city"] = empty($row["city"]) ? "" : $row["city"];            
            $au_item["authors"] = $authors;
            $au_item["feed_id"] = (int)$feed_id;
            $au_item["entry_count"] = (int)$row["entry_count"];
            $au_item["is_viewable"] = (bool)$obj["is_viewable"];
            $au_item["is_viewed"] = $is_viewed;            
            $au_item["youtube_url"] = isset($row["youtube_url"]) ? escapeSolrSpecialChars($row["youtube_url"]) : "";
            
            $au_item["package_names"] = empty($row["package_names"]) ? "" : $row["package_names"];
            $au_item["package_names_text"] = empty($row["package_names_text"]) ? "" : $row["package_names_text"];
            
            $au_item["apple_rating"] = !empty($row["rating_value"]) ? $row["rating_value"] : "";
            $au_item["apple_review"] = !empty($row["review_count"]) ? $row["review_count"] : ""; 
                        
            $au_item["latest_entry_timestamp"] = $row["latest_entry_timestamp"] == "1988-01-01T00:00:00Z" ? "" : solr_to_mysql_time($row["latest_entry_timestamp"]);
            $au_item["last_original_article_creation_date"] = $row["last_original_article_creation_date"] == "1988-01-01T00:00:00Z" ? "" :solr_to_mysql_time($row["last_original_article_creation_date"]);
            $au_item["created"] = solr_to_mysql_time($row["created"]);
            
            $au_item["feed_name"] = $feed_name;
            $au_item["feed_name_html"] = handleSpecialChar($row["feed_name"]);
            $au_item["feed_desc"] = handleSpecialChar($row["feed_desc"]);
            $au_item["author_name"] = !empty($auth_name_arr) ? handleSpecialChar(implode(", ", array_filter($auth_name_arr))) : "";
            $au_item["total_authors"] = $obj["total_authors"];            
            $au_item["lists"] = isset($lists[$feed_id]) ? $lists[$feed_id] : [];
            $au_item["facebook_followers"] = (int)$row["facebook_followers"];
            $au_item["instagram_followers"] = (int)$row["instagram_followers"];
            $au_item["twitter_followers"] = (int)$row["twitter_followers"];
            $au_item["youtube_follower_count"] = (int)$row["youtube_follower_count"];
            $au_item["youtube_video_count"] = (int)$row["youtube_video_count"];
            $au_item["youtube_view_count"] = (int)$row["youtube_view_count"];
            $au_item["review_count"] = (int)$row["review_count"];
            $au_item["apple_id"] = (int)$row["apple_id"];
            $au_item["duration_seconds"] = (int)$row["duration_seconds"];
            $au_item["duration_length"] = seconds_minutes($au_item["duration_seconds"]);
            //$au_item["max_review_count_country"] = !empty($row["max_review_count_country"]) ? map_country_code($row["max_review_count_country"]) : "";
            
            $au_item["max_review_count"] = !empty($row["max_review_count"]) ? $row["max_review_count"] : "";
            $au_item["max_review_count_code"] = $row["max_review_count_country"] ?? "";
            $au_item["max_review_count_country"] = !empty($row["max_review_count_country"]) ? country_by_code($row["max_review_count_country"]) : "";
            $au_item["total_review_count"] = $total_review_count;
            
            $apple_review_count = !empty($au_item["max_review_count"]) ? $au_item["max_review_count"] : "";
            if(!empty($apple_review_count) && !empty($au_item["max_review_count_country"])):
                $apple_review_count .= " (".$au_item["max_review_count_country"].")";
            endif;
            
            $au_item["total_apple_review_count"] = !empty($total_review_count) ? "$total_review_count (Global)" : "";            
            $au_item["apple_review_count"] = $apple_review_count;
            
            $has_sponsor = $has_guest = "Unknown";
            if(isset($row["has_guests"])):
                $has_guest = $row["has_guests"] ? "Yes" : "No";
            endif;
            
            if(isset($row["has_sponsor"])):
                $has_sponsor = $row["has_sponsor"] ? "Yes" : "No";
            endif;            
            
            $au_item["has_guest"] = $has_guest; //$row["has_guests"] ? "Yes" : "Unknown";
            $au_item["has_sponsor"] = $has_sponsor; //$row["has_sponsor"] ? "Yes" : "Unknown";            
            
            $au_item["guest"] = $row["has_guests"] ? true : false;
            $au_item["sponsor"] = $row["has_sponsor"] ? true : false;            
            $au_item["state_code"] = $state_code;
            
            $au_item["video_podcast_url"] = isset($row["video_podcast_url"]) ? $row["video_podcast_url"] : "";
            $au_item["feed_image_url"] = !empty($row["feed_image_url"]) && is_valid_url($row["feed_image_url"]) ? resize_image_url($row["feed_image_url"]) : "";
            $au_item["rss_site_url"] = !empty($au_item["rss_site_url"]) ? $au_item["rss_site_url"] : "";
            
            $guest_names = empty($row["guest_names"]) ? [] : $row["guest_names"];
            $sponsor_names = empty($row["sponsor_names"]) ? [] : $row["sponsor_names"];
            $network_name = empty($row["network_name"]) ? [] : $row["network_name"];
            $network_id = empty($row["network_id"]) ? [] : $row["network_id"];
            
            $beats_feed = isset($beat_feed_mapping[$feed_id]) ? $beat_feed_mapping[$feed_id] : [];
            $community = $beats_name = [];
            
            if(!empty($beats_feed)):
                foreach($beats_feed as $beat_id):
                
                    if(isset($mapping[$beat_id])):
                        $beat_id = $mapping[$beat_id];
                    endif;
                    
                    if(isset($feed_beats[$beat_id])):
                        $beats_name[$beat_id] = $feed_beats[$beat_id]["title"];                    
                    endif;                    
                endforeach;      
                $beats_name = !empty($beats_name) ? array_values($beats_name) : [];
            endif;
            
            if(!empty($guest_names)):
                $guest_names = json_decode($guest_names, 1);
                if(is_array($guest_names)):
                    foreach($guest_names as $gname):
                        $guests[] = handleSpecialChar($gname);
                    endforeach;
                endif;
            endif;
            
            if(!empty($sponsor_names)):
                $sponsor_names = json_decode($sponsor_names, 1);            
                if(is_array($sponsor_names) && !empty($sponsor_names)):
                    foreach($sponsor_names as $sname):
                        $sponsor[] = handleSpecialChar($sname);
                    endforeach;
                endif;
            endif;
            
            $network_option = [];
            if(!empty($network_name)):
                $network_name = json_decode($network_name, 1);
                $network_id = json_decode($network_id, 1);
                
                if(is_array($network_name) && !empty($network_name)):
                    foreach($network_name as $nkey => $nname):
                        $networks[] = handleSpecialChar($nname);
                        $network_option[] = [
                            "title" => handleSpecialChar($nname),
                            "key"   => (int)$network_id[$nkey]
                        ];
                    endforeach;
                endif;
            endif;
            
            $au_item["beats_name"]  = $beats_name;
            $au_item["audience_type"]  = $audience_type;
            $au_item["audience_option"] = $audience_filters;
            $au_item["network_name"]  = $networks;
            $au_item["network_option"]  = $network_option;
            
            $au_item["guest_name"] = $guests;
            $au_item["sponsor_name"] = $sponsor;
            $au_item["sponsor_count"] = isset($row["sponsor_count"]) ? (int)$row["sponsor_count"] : 0;
            $au_item["user_engagement"] = isset($row["estimated_listeners"]) ? $row["estimated_listeners"] : "";
            
            $listener_gender = $listener_age = $listener_income = [];            
            $demographics = !empty($row["demographics_json"]) ? jsonDecode($row["demographics_json"]) : [];
            
            if(!empty($demographics)):
                $gender_distribution = $demographics["gender_distribution"] ?? [];
                $income_distribution = $demographics["income_distribution"] ?? [];
                $listener_generation = $demographics["listener_generation"] ?? [];
                
                foreach($gender_distribution as $gen_d):                
                    $category = $gen_d["category"]; 
                    if(empty($category)):
                        continue;
                    endif;
                    
                    $percentage = !empty($gen_d["percentage"]) ? $gen_d["percentage"]."%" : "";
                    $listener_gender[] = [
                        "key"   => $category,
                        "title" => ucwords($category),
                        "value" => $percentage
                    ]; 
                    $au_item["listener_gender_{$category}"] = $percentage;                    
                endforeach; 
                
                foreach($income_distribution as $inc_d):
                    $inc_item = $ue_ct->get_income("", $inc_d["category"]);
                    if(empty($inc_item)):
                        continue;
                    endif;
                    
                    $category = $inc_d["category"]; 
                    $sheet_key = str_replace(" ", "_", $category);
                    $percentage = !empty($inc_d["percentage"]) ? $inc_d["percentage"]."%" : "";
                    
                    $listener_income[] = [
                        "key"   => $category,
                        "title" => $inc_item["title"],
                        "value" => $percentage
                    ];    
                    $au_item["listener_{$sheet_key}"] = "$percentage"; 
                endforeach;
                
                foreach($listener_generation as $ge_d):
                    $item = $ue_ct->get_age(null, null, $ge_d["category"]);
                    if(empty($item)):
                        continue;
                    endif;             
                    
                    $category = $ge_d["category"];
                    $sheet_key = str_replace(" ", "_", $category);
                    $percentage = !empty($ge_d["percentage"]) ? $ge_d["percentage"]."%" : "";
                    
                    $listener_age[] = [
                        "key"   => $category,
                        "title" => $item["title"],
                        "value" => $percentage
                    ];    
                    $au_item["listener_age_{$sheet_key}"] = "$percentage"; 
                endforeach;                
            endif;
            
            $community_id_array = $row["community_id_array"] ?? [];
            if(!empty($community_id_array)):
                foreach($community_id_array as $comm_id):                
                    $crow = $ue_ct->get_community($comm_id);
                    if(!empty($crow)):
                        $community[] = $crow;                        
                    endif;
                endforeach;            
            endif;
            
            $au_item["community"] = $community;
            $au_item["listener_gender"] = $listener_gender;
            $au_item["listener_income"] = $listener_income;
            $au_item["listener_age"] = $listener_age;
            $au_item["years_active_display"] = isset($row["years_active_display"]) ? $row["years_active_display"] : "";
            
            if($contact_page):
                $au_item["review_graph"] = $review_graph;            
            endif;
            
            $keys = unset_key();
            if(!empty($keys)):
                foreach($keys as $key):
                    unset($au_item[$key]);
                endforeach;
            endif;
            
            $records = $au_item;
            if($is_search):
                $records = [
                    "feed_id"                   => $feed_id,
                    "feed_name"                 => $feed_name,
                    "author_name"               => $au_item["author_name"],
                    "location"                  => $au_item["location"],
                    "apple_rating"              => $au_item["apple_rating"],
                    "apple_review"              => $au_item["apple_review"],
                    "idx"                       => $au_item["idx"],
                    "is_viewed"                 => $au_item["is_viewed"],
                    "is_viewable"               => $au_item["is_viewable"],
                    "feed_image_url"            => $au_item["feed_image_url"],
                    "lists"                     => $au_item["lists"],
                    "max_review_count"          => $au_item["max_review_count"],
                    "max_review_count_country"  => $au_item["max_review_count_country"],
                ];                
            endif;                
            $data[] = $records;
        endforeach;
        
        return $data;        
    }
endif;

if(!function_exists("map_country_code")):
    function map_country_code($country_code) {
        $country_code = strtoupper($country_code);
        $arr = [
            "GB"    => "UK",
        ];        
        if(isset($arr[$country_code]) && !empty($arr[$country_code])):
            return $arr[$country_code];
        endif;
        
        return $country_code;
    }
endif;

if(!function_exists("unset_key")):
    function unset_key() {
        $arr = ["_version_", "review_count", "listener_generation", "percentage", "audience_type_id_array", "sponsor_names", "package_names", "package_names_text", "social_eng", "alexa_rank", "feed_url_text", "guest_names", "has_guests", "email_json_txt", "network_id", "itune_contact_json", "beats_id", "categories", "apple_country_code", "da", "also_in_blog", "email_json", "apple_chart_updated_date", "apple_chart_rank"];    
        return $arr;
    }
endif;

if(!function_exists("seconds_minutes")):
    function seconds_minutes($seconds) {    
        if(empty($seconds)):
            return "";
        endif;
        
        $minutes = ceil($seconds / 60); 
        return $minutes . ' mins';
    }
endif;

if(!function_exists("solr_to_mysql_time")):
    function solr_to_mysql_time($datetime) {
        $datetime = str_replace("Z","",str_replace("T"," ",$datetime));
        return $datetime;
    }
endif;

if(!function_exists("trim_string")):
    function trim_string($txt, $limit = 300) {
        preg_match_all("/[A-Z]/", $txt, $r);
        if(count($r[0])<5):$limit += 3;endif;
        
        if(empty($txt)):
            return $txt;
        endif;
        
        $len = strlen($txt);
        if($len > $limit):
            $tmptxt = substr($txt, 0, $limit - 3);
            if($len > 80):
                $x = strrpos($tmptxt, " ");
                if($x):$tmptxt = substr($tmptxt, 0, $x);endif;
            endif;
            $txt = $tmptxt.'...';
        endif;
        
        return $txt;
    }
endif;


if (!function_exists("build_email_snippet")):
    function build_email_snippet($html = '', $text = '', $limit = 120, $append_dot = 1) {    
        $content = !empty($text) ? $text : $html;
    
        if(empty($content)):
            return '';
        endif;
    
        $content = strip_tags($content);    
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
    
        if(strlen($content) > $limit) {
            $tmp = substr($content, 0, $limit - 3);    
            $space = strrpos($tmp, " ");
            if($space !== false):
                $tmp = substr($tmp, 0, $space);
            endif;
            
            $content = $append_dot ? $tmp . '...' : $tmp;
        }    
        return $content;
    }
endif;

if(!function_exists("trimValues")):
    function trimValues($data) {
        $data = is_array($data) ? array_map('trim', $data) : trim($data);
        $result = is_array($data) ? array_map('strtolower', $data) : strtolower($data);
        return $result;
    }
endif;

if(!function_exists("instagramHandle")):
    function instagramHandle($url) {
        $arr = parse_url(trimValues($url));
        $handle = trim($arr["path"], "/");        
        $handle = empty($handle) ? "" : $handle;
        return $handle;
    }
endif;

if(!function_exists("remove_query_string")):
    function remove_query_string($url) {
        $parts = parse_url($url);
        $clean_url = $parts['scheme'] . '://' . $parts['host'];
        if(!empty($parts['path'])):
            $clean_url .= $parts['path'];
        endif;
        return $clean_url;
    }
endif;

if(!function_exists("clean_url")):
    function clean_url($url) {
        $url = preg_replace('#^https?://#', '', $url);    
        $url = preg_replace('#^www\.#', '', $url);    
        $url = rtrim($url, '/');    
        return $url;
    }
endif;

if(!function_exists("twitterHandle")):
    function twitterHandle($url) {
        $handle = "";
        if(preg_match("/^https?:\/\/(www\.)?twitter\.com\/(#!\/)?(?<name>[^\/]+)(\/\w+)*$/", $url, $regs)):
            $handle = $regs['name'];
        endif;
        return $handle;
    }
endif;

if (!function_exists("facebookHandle")):
    function facebookHandle($url) {
        $url = trim($url, "/");
        $y = [];
        if (preg_match("/\.facebook\.com\/pages\//i", $url)):

            $x = explode("www.facebook.com/pages/", $url);

            if (!empty($x[1])):
                $z = explode("/", $x[1]);

                if (!empty($z[1])) {
                    $y = explode("&", $z[1]);
                }
            endif;

        else:

            $x = explode("facebook.com/", $url);

            if (!empty($x[1])) {
                $y = explode("&", trim($x[1], '/'));
            }

            if (!empty($y[0])):

                if (preg_match("/^[a-zA-Z0-9\.]+$/", $y[0])):
                    // valid username

                elseif (preg_match("/^[a-zA-Z0-9\.\-]+$/", $y[0])):
                    $name_a = explode("-", $y[0]);
                    $id = $name_a[count($name_a) - 1];

                    if (is_numeric($id)):
                        $y[0] = $id;
                    endif;

                elseif (preg_match("/profile\.php\?id=/i", $y[0])):
                    $m = explode("profile.php?id=", $y[0]);

                    if (!empty($m[1]) && is_numeric($m[1])):
                        $y = [];
                        $y[0] = $m[1];
                    endif;

                endif;

            endif;

        endif;

        // 🔥 FINAL SAFE ACCESS
        $handle = $y[0] ?? "";

        if (!empty($handle)) {
            $handle = trimValues($handle);
            $handle = trim($handle, "/");
            $handle = strtolower($handle);
            $handle = strpos($handle, '?') !== false ? strtok($handle, '?') : $handle;
        }

        return $handle;
    }
endif;

if(!function_exists("linkedinHandle")):
    function linkedinHandle($url) {    
        /*$pattern = '/((http?|https)\:\/\/)?([a-zA-Z]+)\.linkedin.com\/company\/[a-zA-Z0-9]{5,30}/i';
        preg_match($pattern, $url, $result);
        $handle = count($result) != 0 ? basename(parse_url($result[0], PHP_URL_PATH)) : "";
        return $handle;*/
        $pattern = '/linkedin\.com\/(?:company|in|school|showcase)\/([a-zA-Z0-9\-_%]+)/i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? rtrim($matches[1], '/') : '';
    }
endif;

if(!function_exists("youtubeHandle")):
    function youtubeHandle($url) {
        $username = "";
        $youtube_url = $url;
        $url = str_replace("@", "", $url);
        
        if(preg_match("/^https\:\/\/www\.youtube\.com\/user\//i", $url)):
            preg_match_all("/^http[s]?:\/\/(www\.)?youtube.com\/user\/([a-zA-Z0-9\-\'\.\_]+)\/?/i", $url, $rs);
            $username = $rs[2][0];
        elseif(preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/channel\/[a-zA-Z0-9\-\'\.\_]+\/?/i", $url)):
            preg_match_all("/^http[s]?:\/\/(www\.)?youtube.com\/channel\/([a-zA-Z0-9\-\'\.\_]+)\/?/i", $url, $rs);
            $username = $rs[2][0];
        elseif(preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/channel\/[a-zA-Z0-9\-\'\.\_]+\/?/i", $url) || preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/feeds\/videos\.xml\?channel_id=[a-zA-Z0-9\-\'\.\_]+\/?/i", $url)):
            if(preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/channel\/[a-zA-Z0-9\-\'\.\_]+\/?/i", $url)):
                preg_match_all("/^http[s]?:\/\/(www\.)?youtube.com\/channel\/([a-zA-Z0-9\-\'\.\_]+)\/?/i", $url, $result);
                $username = $result[2][0];
            elseif(preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/feeds\/videos\.xml\?channel_id=[a-zA-Z0-9\-\'\.\_]+\/?/i", $url)):
                preg_match_all("/^http[s]?:\/\/(www\.)?youtube.com\/feeds\/videos\.xml\?channel_id=([a-zA-Z0-9\-\'\.\_]+)\/?/i", $url, $result);
                $username = $result[2][0];
            endif;
        elseif(preg_match("/^http[s]?:\/\/(www\.)?youtube.com\/[a-zA-Z0-9]/i", $url)):
            $url = explode("/", $url);
            if($url[3] == "c"):
                $username = $url[4];
            elseif(!empty($url[3]) && isset($url[4]) && ($url[4] == "shorts" || $url[4] == "videos")):
                $username = $url[3];
            elseif(isset($url[3]) && (!isset($url[4]) || ($url[4]=='' && !isset($url[5])))):
                if(strpos($url[3], "playlist") !== false):
                else:
                    $username = $url[3];
                endif;            
            endif;
        endif;   
        
        if(empty($username)):
            $patterns = [
                '/youtube\.com\/@([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/user\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/c\/([a-zA-Z0-9_-]+)/i',
                '/youtube\.com\/channel\/([a-zA-Z0-9_-]+)/i'
            ];
            foreach($patterns as $pattern):
                if(preg_match($pattern, $youtube_url, $matches)):
                    $username = $matches[1];
                    break;
                endif;
            endforeach;
        endif;
        
        return $username;
    }

endif;

if(!function_exists("jsonDecode")):
    function jsonDecode($data, $arr = true) {
        return json_decode($data, $arr);
    }
endif;

if(!function_exists("planDataName")):
    function planDataName($type, $plan) {
        $name = $type."_".$plan["plan_pref"]."_".$plan["cycle"];
        $name = strtolower($name);
        return $name;
    }
endif;

if(!function_exists("is_twc_user")):
    function is_twc_user($bucket) {
        $arr = [\App\Enums\BucketEnum::TRIAL_WITHOUT_CARD, \App\Enums\BucketEnum::PLAN_CREDIT_INCREMENT, \App\Enums\BucketEnum::PLAN_CHANGES_V1, \App\Enums\BucketEnum::REVERTED_BACKTO_NORMAL];        
        return in_array($bucket, $arr);
    }
endif;

if(!function_exists("hide_credits")):
    function hide_credits($bucket) {
        $arr = [\App\Enums\BucketEnum::REMOVED_TWC, \App\Enums\BucketEnum::REMOVED_LOWER_PLAN];
        return in_array($bucket, $arr);
    }
endif;

if(!function_exists("get_from_email")):
    function get_from_email($template_id) {
        $team_arr = [
            \App\Enums\EmailTemplateEnum::WELCOME,
        ];
        
        $nobody_arr = [
            \App\Enums\EmailTemplateEnum::ACCOUNT_CONFIRMATION,
            \App\Enums\EmailTemplateEnum::REQUEST_OTP,
            \App\Enums\EmailTemplateEnum::REACTIVATE_ACCOUNT,
            \App\Enums\EmailTemplateEnum::DELETE_ACCOUNT_TRIAL,
            \App\Enums\EmailTemplateEnum::MAGIC_LINK,
            \App\Enums\EmailTemplateEnum::NOTIFY_EXPORT,
            \App\Enums\EmailTemplateEnum::FORGOT_PASSWORD,  
            \App\Enums\EmailTemplateEnum::DEACTIVATE_ACCOUNT,
            \App\Enums\EmailTemplateEnum::REACTIVATE_ACCOUNT,            
            \App\Enums\EmailTemplateEnum::WELCOME_QUICK_START,
            \App\Enums\EmailTemplateEnum::SHOWCASE_THE_VALUE,
            \App\Enums\EmailTemplateEnum::HIGHLIGHT_FEATURE,
            \App\Enums\EmailTemplateEnum::LIMITED_TIME_OFFER,
            \App\Enums\EmailTemplateEnum::LAST_CHANCE_FOR_DEMO,
            \App\Enums\EmailTemplateEnum::TWC_ENDS_TOMORROW,
            \App\Enums\EmailTemplateEnum::TWC_ENDS_TODAY,
            \App\Enums\EmailTemplateEnum::TWC_EXPIRED,
        ];
        
        $payments_arr = [
            \App\Enums\EmailTemplateEnum::SUBSCRIPTION_SUCCESS,
            \App\Enums\EmailTemplateEnum::SUBSCRIPTION_UPGRADE,
            \App\Enums\EmailTemplateEnum::SUBSCRIPTION_DOWNGRADE,     
            \App\Enums\EmailTemplateEnum::SUBSCRIPTION_CANCEL,
        ];
        
        $pricing_support_arr = [
            \App\Enums\EmailTemplateEnum::UPGRADE_SUPPORT_EMAIL,
            \App\Enums\EmailTemplateEnum::DEMO_REQUEST,
        ];
        
        $email = SES_FROM_EMAIL;
        $name = SES_FROM_NAME;
        if(in_array($template_id, $team_arr)):
            $email = TO_TEAM_EMAIL;
            $name = TO_TEAM_NAME;
        elseif(in_array($template_id, $nobody_arr)):
            $email = TO_NOBODY_EMAIL;
            $name = TO_NOBODY_NAME;
        elseif(in_array($template_id, $payments_arr)):
            $email = TO_PAYMENTS_EMAIL;
            $name = TO_PAYMENTS_NAME;
        elseif(in_array($template_id, $pricing_support_arr)):
            $email = TO_SUPPORT_PRICING_EMAIL;
            $name = TO_SUPPORT_PRICING_NAME;
        endif;
        
        return ["name" => $name, "email" => $email];
    }
endif;

if(!function_exists("explode_token")):
    function explode_token($token) {
        $arr = explode("---", decryptString($token));
        return $arr;
    }
endif;

if(!function_exists("is_active_user")):
    function is_active_user($status) {
        $is_active = !empty($status) && in_array($status, [\App\Enums\AccountStatusEnum::ACTIVE, \App\Enums\AccountStatusEnum::DEACTIVATED]) ? true : false;
        return $is_active;
    }
endif;

if(!function_exists("isAdmin")):
    function isAdmin($auto_id) {
        $is_admin = !empty($auto_id) && in_array($auto_id, ADMIN_IDS) ? true : false;
        return $is_admin;
    }
endif;

if(!function_exists("get_same_plan_vv_cycle")):
    function get_same_plan_vv_cycle($plans, $plan_type, $cycle) {
        foreach($plans as $plan):
            if($plan["plan_type"] == $plan_type && $plan["cycle"] == $cycle):
                return $plan;
            endif;    
        endforeach;        
        return false;
    }
endif;

if(!function_exists("is_tier_one")):
    function is_tier_one($payment = []) {
        $payment = !is_array($payment) ? (array)$payment : $payment; 
        
        if(!empty($payment)):            
            if($payment["tier"] == 1):            
                return true;
            else:
                return false;
            endif;
        endif;
        
        if(!ENABLE_TIER_PRICING):
            return true;
        endif;
        
        $tier_one_arr = ["US", "CA", "GB", "AU", "SG", "IE", "CH", "NO", "HK", "DK", "NL", "AT", "DE", "SE", "FR", "BE", "JP", "NZ", "AE", "IL"];
        $country_code = get_ip_details()["country_code"] ?? "";
       
        if(empty($country_code) || in_array($country_code, $tier_one_arr)):
            return true;
        endif;
        
        return false;        
    }
endif;

if(!function_exists("plans")):
    function plans($auto_id = 0, $sort = 0, $include_free_plan = 0, $bucket = LATEST_PLAN_BUCKET, $payment = null) {    
        
        $contact = \App\Enums\RestrictTypeEnum::CONTACT;  
        $export = \App\Enums\RestrictTypeEnum::EXPORT;  
        $search = \App\Enums\RestrictTypeEnum::SEARCH;  
        $list = \App\Enums\RestrictTypeEnum::LIST; 
        $cycle = \App\Enums\RestrictTypeEnum::CYCLE;
        $list_contact = \App\Enums\RestrictTypeEnum::LIST_CONTACT;
        $search_result = \App\Enums\RestrictTypeEnum::SEARCH_RESULT;
        $has_video = \App\Enums\RestrictTypeEnum::HAS_VIDEO;
        $has_guest = \App\Enums\RestrictTypeEnum::HAS_GUEST;
        $has_sponser = \App\Enums\RestrictTypeEnum::HAS_SPONSER;
        $is_admin = empty($auto_id) ? false : isAdmin($auto_id);
        $ct = new \App\Enums\CreditEnum;            
        
        $is_tier_1 = is_tier_one($payment);
        //echo "$is_tier_1<pre>"; print_r($payment); die;
        
        $data = [
            $contact        => [
                "text"      => "Get {{{$contact}}} Podcasts Emails per {{{$cycle}}}",
                "checked"   => true, 
                "plan_data" => [$contact, $cycle],
                "bold_data" => [$contact],
                "st_paid"   => ["plans" => [TIER2_BUSINESS_PLUS_YEARLY, TIER2_BUSINESS_PLUS_YEARLY + ADMIN_PLAN_NO, BUSINESS_PLUS_YEAR_V3, BUSINESS_PLUS_YEAR_V3 + ADMIN_PLAN_NO, BUSINESS_PLUS_YEAR, BUSINESS_PLUS_YEAR + ADMIN_PLAN_NO], "text" => "Unlimited Podcasts Emails per {{{$cycle}}}", "tooltip_text" => ""],
                "tooltip"   => "View or export verified podcast contact details. Each time you view a podcast email or export a podcast, one credit is used. Each {{{$cycle}}} {{{$contact}}} podcast email credits will be added to your account.",
            ],
            $list           => [
                "text"      => "{{{$list}}} Lists",
                "checked"   => true,
                "plan_data" => [$list],
                "st_paid"   => ["plans" => HIGHER_PLANS, "text" => "Unlimited Lists"],
                "tooltip"   => "", //"Build custom lists of podcasts that are relevant to your campaign. You can keep them organized, share with teammates, or send to clients with just a export click.",
            ],
            $export         => [
                "text"      => "Export up to {{{$contact}}} Podcasts per {{{$cycle}}}",
                "plan_data" => [$contact, $cycle],
                "bold_data" => [$contact],
                "st_paid"   => ["plans" => [TIER2_BUSINESS_PLUS_YEARLY, TIER2_BUSINESS_PLUS_YEARLY + ADMIN_PLAN_NO, BUSINESS_PLUS_YEAR_V3, BUSINESS_PLUS_YEAR_V3 + ADMIN_PLAN_NO, BUSINESS_PLUS_YEAR, BUSINESS_PLUS_YEAR + ADMIN_PLAN_NO], "text" => "Export Unlimited Podcasts per {{{$cycle}}}"],
                "checked"   => true,
                "tooltip"   => "", //"Download your podcast list in Excel or CSV. You can import it in your CRM or any email sending client.",
            ],
            $search         => [
                "text"      => "{{{$search}}} Searches/Month",
                "checked"   => true,
                "plan_data" => [$search],
                "st_paid"   => ["plans" => HIGHER_PLANS, "text" => "Unlimited Searches/Month"],
                "st_free"   => ["plans" => FREE_PLANS, "text" => "Limited Searches"],
                "tooltip"   => "Every time you search for a new keyword, it counts as one search. Searching the same keyword again still counts only once, even if you apply different filters. Once you hit your monthly search limit, you won't be able to search again until next month, but your saved lists and other features will continue to work.",
            ],            
            $search_result  => [
                "text"      => "{{{$search_result}}} Search Results per query",
                "checked"   => true,
                "plan_data" => [$search_result],                
                "st_paid"   => ["plans" => HIGHER_PLANS, "text" => "Unlimited Search Result per query", "tooltip_text" => "You will be able to see unlimited podcasts relevant to your query."],                
                "st_free"   => ["plans" => FREE_PLANS, "text" => "Limited Search Results"],
                "tooltip"   => "You will be able to see {{{$search_result}}} unique podcasts relevant to your query.",
            ],            
            
            /*\App\Enums\RestrictTypeEnum::CONCIERGE  => [
                "text"          => "Concierge Service",
                "checked"       => true,
                "tooltip"       => "1.You can ask our team of researchers to go out and find the best and most up-to-date contact information for any podcast, saving you hours of time and effort.<br>2.You can ask our team of researchers to help you build a targeted podcast list for you.<br>3.Slack/Telegram Priority Channel.",
            ],*/   
            
            /*\App\Enums\RestrictTypeEnum::VERIFIED => [
                "text"      => "Access to verified host & producer contacts",
                "checked"   => true,
                "tooltip"   => "Direct email and social profiles of podcast hosts, producers, and booking contacts. Data is regularly verified and updated.",
            ],
            \App\Enums\RestrictTypeEnum::ADVANCED => [
                "text"      => "Advanced podcast qualification filters",
                "checked"   => true,
                "tooltip"   => "Narrow results using adv. filters such as guest acceptance, sponsorships, youtube presence, location, and contact availability.",
            ],
            \App\Enums\RestrictTypeEnum::AUDIENCE => [
                "text"      => "Audience size & reach targeting",
                "checked"   => true,
                "tooltip"   => "Filter podcasts by estimated monthly listeners to match your outreach with the right audience size.",
            ],
            \App\Enums\RestrictTypeEnum::SUPPORT => [
                "text"      => "Priority support & faster data updates",
                "checked"   => true,
                "tooltip"   => "Get quicker responses from our support team",
            ],*/
            /*$has_video      => [
                "text"      => "Has Video Podcast Filter",
                "checked"   => true
            ],
            $has_guest      => [
                "text"      => "Has Guest Filter",
                "checked"   => true
            ],
            $has_sponser    => [
                "text"      => "Has Sponsors Filter",
                "checked"   => true
            ],*/
        ];
        
        //depricated
        $business_month = BUSINESS_MONTH;
        $business_year = BUSINESS_YEAR;
        
        $starter_month = STARTER_MONTH;
        $starter_year = STARTER_YEAR;
        $starter_year_v3 = STARTER_YEAR_V3;
        $starter_year_v4 = STARTER_YEAR_V4;
        
        $basic_month = BASIC_MONTH;
        $basic_year = BASIC_YEAR;
        $basic_year_v3 = BASIC_YEAR_V3;
        $basic_year_v4 = BASIC_YEAR_V4;
        
        $business_plus_month = BUSINESS_PLUS_MONTH;
        $business_plus_year = BUSINESS_PLUS_YEAR;
        $business_plus_year_v3 = BUSINESS_PLUS_YEAR_V3;
        $business_plus_year_v4 = BUSINESS_PLUS_YEAR_V4;
        
        $pro_month_v2 = PRO_MONTH_V2;
        $pro_year_v2 = PRO_YEAR_V2;
        $pro_year_v3 = PRO_YEAR_V3;
        $pro_year_v4 = PRO_YEAR_V4;
        
        if(!$is_tier_1):
            $starter_month = TIER2_STARTER_MONTHLY;
            $starter_year_v4 = $starter_year = $starter_year_v3 = TIER2_STARTER_YEARLY;
            
            $pro_month_v2 = TIER2_PRO_MONTHLY;
            $pro_year_v4 = $pro_year_v2 = $pro_year_v3 = TIER2_PRO_YEARLY;
            
            $basic_month = TIER2_BUSINESS_MONTHLY;
            $basic_year_v4 = $basic_year = $basic_year_v3 = TIER2_BUSINESS_YEARLY;
            
            $business_plus_month = TIER2_BUSINESS_PLUS_MONTHLY;
            $business_plus_year_v4 = $business_plus_year = $business_plus_year_v3 = TIER2_BUSINESS_PLUS_YEARLY;
        endif;
        
        if($is_admin):
            $business_month += ADMIN_PLAN_NO;
            $business_year += ADMIN_PLAN_NO;
            
            $starter_month += ADMIN_PLAN_NO;
            $starter_year += ADMIN_PLAN_NO;
            $basic_month += ADMIN_PLAN_NO;
            $basic_year += ADMIN_PLAN_NO;
            $business_plus_month += ADMIN_PLAN_NO;
            $business_plus_year += ADMIN_PLAN_NO;
            $pro_month_v2 += ADMIN_PLAN_NO;
            $pro_year_v2 += ADMIN_PLAN_NO;
            
            $starter_year_v3 += ADMIN_PLAN_NO;
            $pro_year_v3 += ADMIN_PLAN_NO;  
            $basic_year_v3 += ADMIN_PLAN_NO;            
            $business_plus_year_v3 += ADMIN_PLAN_NO;  
            
            $starter_year_v4 += ADMIN_PLAN_NO;
            $pro_year_v4 += ADMIN_PLAN_NO;  
            $basic_year_v4 += ADMIN_PLAN_NO;            
            $business_plus_year_v4 += ADMIN_PLAN_NO;    
        endif;
        
        ################## Free ##################
        
        if($include_free_plan):        
            $plans[] = [
                "active"        => true,
                "plan_pref"     => FREE_MONTH,
                "plan_name"     => PLAN_NAME_FREE,
                "description"   => "Get started with basic access",
                "cycle"         => "month",
                "symbol"        => "$",
                "amount"        => 0,
                "currency"      => "USD",
                "trial"         => false,
                "pay_now"       => false,
                "data"          => $data,
                "plan_type"     => 1,
                "order"         => 0,
                "can_switch"    => false,
                $list           => 3,     
                $list_contact   => MAX_LIST_CONTACT,
                $search         => 10,
                $contact        => REGISTRATION_CREDITS,
                $export         => false,
                $search_result  => FREE_USER_LIMIT,
                $has_video      => false,
                $has_guest      => false,
                $has_sponser    => false,
            ];            
            $plans[] = [
                "active"        => true,
                "plan_pref"     => FREE_YEAR,
                "plan_name"     => PLAN_NAME_FREE,
                "description"   => "Get started with basic access",
                "cycle"         => "year",
                "symbol"        => "$",
                "amount"        => 0,
                "currency"      => "USD",
                "trial"         => false,
                "pay_now"       => false,
                "data"          => $data,
                "plan_type"     => 1,
                "order"         => 1,
                "can_switch"    => false,
                $list           => 3,
                $list_contact   => MAX_LIST_CONTACT,
                $search         => 10,
                $contact        => REGISTRATION_CREDITS,
                $export         => false,      
                $search_result  => FREE_USER_LIMIT,                
                $has_video      => false,
                $has_guest      => false,
                $has_sponser    => false,
            ];        
        endif;
        
        ################## Business month / deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $business_month,
            "plan_name"     => $ct->get_plan_name($business_month, $bucket),
            "description"   => $ct->get_plan_desc($business_month, $bucket),
            "cycle"         => "month",
            "symbol"        => "$",
            "amount"        => $is_admin ? 2 : 499,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 5,
            "order"         => 200,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($business_month, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($business_month, $bucket),
            $contact        => $ct->get_bucket_credit($business_month, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($business_month, $bucket),            
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
        ];        
        
        ################## Business year / deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $business_year,
            "plan_name"     => $ct->get_plan_name($business_year, $bucket),
            "description"   => $ct->get_plan_desc($business_year, $bucket),
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 20 : 4990,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 5,
            "order"         => 202,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($business_year, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($business_year, $bucket),
            $contact        => $ct->get_bucket_credit($business_year, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($business_year, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
        ];        
        
        ################## Business Plus Month ##################
        
        $plans[] = [
            "active"        => SHOW_MONTHLY_PLAN ? true : false,
            "plan_pref"     => $business_plus_month,
            "plan_name"     => $ct->get_plan_name($business_plus_month, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($business_plus_month, $bucket) : "",
            "cycle"         => "month",
            "symbol"        => "$",
            "amount"        => $is_admin ? 3 : ($is_tier_1 ? 249 : 125),
            "strike_amount" => $is_tier_1 ? 499 : 249,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 6,
            "order"         => 250,
            "can_switch"        => SHOW_MONTHLY_PLAN ? true : false,
            $list               => $ct->get_plan_list($business_plus_month, $bucket),
            $list_contact       => MAX_LIST_CONTACT,
            $search             => $ct->get_plan_search($business_plus_month, $bucket),
            $contact            => $ct->get_bucket_credit($business_plus_month, $bucket),
            $export             => true,
            $search_result      => $ct->get_plan_search_result($business_plus_month, $bucket),
            $has_video          => true,
            $has_guest          => true,
            $has_sponser        => true,
            "locked_filters"    => []
        ];
        
        ################## Business Plus Year / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $business_plus_year,
            "plan_name"     => $ct->get_plan_name($business_plus_year, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($business_plus_year, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 30 : 2490,
            "strike_amount" => 416,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 6,
            "order"         => 251,
            "can_switch"    => false,
            $list           => $ct->get_plan_list($business_plus_year, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($business_plus_year, $bucket),
            $contact        => $ct->get_bucket_credit($business_plus_year, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($business_plus_year, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
        ];        
        
        ################## Business Plus Year 40% / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $business_plus_year_v3,
            "plan_name"     => $ct->get_plan_name($business_plus_year_v3, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($business_plus_year_v3, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 4 : ($is_tier_1 ? 1793 : 900),
            "strike_amount" => $is_tier_1 ? 416 : 150,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 6,
            "order"         => 251,
            "can_switch"    => false,
            $list           => $ct->get_plan_list($business_plus_year_v3, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($business_plus_year_v3, $bucket),
            $contact        => $ct->get_bucket_credit($business_plus_year_v3, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($business_plus_year_v3, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => []
        ]; 
        
        ################## Business Plus Year 40% / V4 ##################
        
        $plans[] = [
            "active"        => true,
            "plan_pref"     => $business_plus_year_v4,
            "plan_name"     => $ct->get_plan_name($business_plus_year_v4, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($business_plus_year_v4, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 4 : ($is_tier_1 ? 1788 : 900),
            "strike_amount" => $is_tier_1 ? 416 : 150,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 6,
            "order"         => 251,
            "can_switch"    => true,
            $list           => $ct->get_plan_list($business_plus_year_v4, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($business_plus_year_v4, $bucket),
            $contact        => $ct->get_bucket_credit($business_plus_year_v4, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($business_plus_year_v4, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => []
        ]; 
        
        ################## Pro Month ##################
        
        $plans[] = [
            "active"        => SHOW_MONTHLY_PLAN ? true : false,
            "plan_pref"     => $pro_month_v2,
            "plan_name"     => $ct->get_plan_name($pro_month_v2, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($pro_month_v2, $bucket) : "",
            "cycle"         => "month",
            "symbol"        => "$",
            "amount"        => $is_admin ? 2 : ($is_tier_1 ? 49 : 25),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 3,
            "order"         => 3,
            "can_switch"    => true, //SHOW_MONTHLY_PLAN ? true : false,
            $list           => $ct->get_plan_list($pro_month_v2, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($pro_month_v2, $bucket),
            $contact        => $ct->get_bucket_credit($pro_month_v2, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($pro_month_v2, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => LOCKED_FEATURES_PRO
        ];
        
        ################## Pro Year / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $pro_year_v2,
            "plan_name"     => $ct->get_plan_name($pro_year_v2, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($pro_year_v2, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 20 : 490,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 3,
            "order"         => 4,
            "can_switch"    => false,
            $list           => $ct->get_plan_list($pro_year_v2, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($pro_year_v2, $bucket),
            $contact        => $ct->get_bucket_credit($pro_year_v2, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($pro_year_v2, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
        ];
        
        ################## Pro Year 40% / Deprecated ##################
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $pro_year_v3,
            "plan_name"     => $ct->get_plan_name($pro_year_v3, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($pro_year_v3, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 2 : ($is_tier_1 ? 353 : 180),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 3,
            "order"         => 4,
            "can_switch"    => false,
            $list           => $ct->get_plan_list($pro_year_v3, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($pro_year_v3, $bucket),
            $contact        => $ct->get_bucket_credit($pro_year_v3, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($pro_year_v3, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => LOCKED_FEATURES_PRO
        ];
        
        ################## Pro Year 40%  / V4 ##################
        $plans[] = [
            "active"        => true,
            "plan_pref"     => $pro_year_v4,
            "plan_name"     => $ct->get_plan_name($pro_year_v4, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($pro_year_v4, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 2 : ($is_tier_1 ? 348 : 180),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 3,
            "order"         => 4,
            "can_switch"    => true,
            $list           => $ct->get_plan_list($pro_year_v4, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($pro_year_v4, $bucket),
            $contact        => $ct->get_bucket_credit($pro_year_v4, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($pro_year_v4, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => LOCKED_FEATURES_PRO
        ];
        
        ################## Starter Month / ##################
        
        $plans[] = [
            "active"        => true,
            "plan_pref"     => $starter_month,
            "plan_name"     => $ct->get_plan_name($starter_month, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($starter_month, $bucket) : "",
            "cycle"         => "month",
            "symbol"        => "$",
            "amount"        => $is_admin ? 1 : ($is_tier_1 ? 19 : 10),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 2,
            "order"         => 1,
            "can_switch"    => true, 
            $list           => $ct->get_plan_list($starter_month, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($starter_month, $bucket),
            $contact        => $ct->get_bucket_credit($starter_month, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($starter_month, $bucket),
            $has_video      => false,
            $has_guest      => false,
            $has_sponser    => false,
            "locked_filters"    => LOCKED_FEATURES_STARTER
        ];        
        
        ################## Starter Year / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $starter_year,
            "plan_name"     => $ct->get_plan_name($starter_year, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($starter_year, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 3 : 190,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 2,
            "order"         => 2,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($starter_year, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($starter_year, $bucket),
            $contact        => $ct->get_bucket_credit($starter_year, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($starter_year, $bucket),
            $has_video      => false,
            $has_guest      => false,
            $has_sponser    => false,
        ];
        
        ################## Starter Year 40% / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $starter_year_v3,
            "plan_name"     => $ct->get_plan_name($starter_year_v3, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($starter_year_v3, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 1 : ($is_tier_1 ? 137 : 72),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 2,
            "order"         => 2,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($starter_year_v3, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($starter_year_v3, $bucket),
            $contact        => $ct->get_bucket_credit($starter_year_v3, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($starter_year_v3, $bucket),
            $has_video      => false,
            $has_guest      => false,
            $has_sponser    => false,
            "locked_filters"    => LOCKED_FEATURES_STARTER
        ];
        
        ################## Starter Year 40% / V4 ##################
        
        $plans[] = [
            "active"        => true,
            "plan_pref"     => $starter_year_v4,
            "plan_name"     => $ct->get_plan_name($starter_year_v4, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($starter_year_v4, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 1 : ($is_tier_1 ? 132 : 72),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 2,
            "order"         => 2,
            "can_switch"    => true, 
            $list           => $ct->get_plan_list($starter_year_v4, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($starter_year_v4, $bucket),
            $contact        => $ct->get_bucket_credit($starter_year_v4, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($starter_year_v4, $bucket),
            $has_video      => false,
            $has_guest      => false,
            $has_sponser    => false,
            "locked_filters"    => LOCKED_FEATURES_STARTER
        ];
        
        ################## Business Month ##################
        
        $plans[] = [
            "active"        => SHOW_MONTHLY_PLAN ? true : false,
            "plan_pref"     => $basic_month,
            "plan_name"     => $ct->get_plan_name($basic_month, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($basic_month, $bucket) : "",
            "cycle"         => "month",
            "symbol"        => "$",
            "amount"        => $is_admin ? 3 : ($is_tier_1 ? 99 : 50),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 4,
            "order"         => 5,
            "can_switch"    => SHOW_MONTHLY_PLAN ? true : false, 
            $list           => $ct->get_plan_list($basic_month, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($basic_month, $bucket),
            $contact        => $ct->get_bucket_credit($basic_month, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($basic_month, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => [],
        ];        
        
        ################## Business Year / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $basic_year,
            "plan_name"     => $ct->get_plan_name($basic_year, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($basic_year, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 30 : 990,
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 4,
            "order"         => 6,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($basic_year, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($basic_year, $bucket),
            $contact        => $ct->get_bucket_credit($basic_year, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($basic_year, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
        ];
        
        ################## Business Year 40% / Deprecated ##################
        
        $plans[] = [
            "active"        => false,
            "plan_pref"     => $basic_year_v3,
            "plan_name"     => $ct->get_plan_name($basic_year_v3, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($basic_year_v3, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 3 : ($is_tier_1 ? 713 : 360),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 4,
            "order"         => 6,
            "can_switch"    => false, 
            $list           => $ct->get_plan_list($basic_year_v3, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($basic_year_v3, $bucket),
            $contact        => $ct->get_bucket_credit($basic_year_v3, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($basic_year_v3, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => [],
        ];
        
        ################## Business Year 40% / V4 ##################
        
        $plans[] = [
            "active"        => true,
            "plan_pref"     => $basic_year_v4,
            "plan_name"     => $ct->get_plan_name($basic_year_v4, $bucket),
            "description"   => SHOW_MONTHLY_PLAN ? $ct->get_plan_desc($basic_year_v4, $bucket) : "",
            "cycle"         => "year",
            "symbol"        => "$",
            "amount"        => $is_admin ? 3 : ($is_tier_1 ? 708 : 360),
            "currency"      => "USD",
            "trial"         => false,
            "pay_now"       => true,
            "data"          => $data,
            "plan_type"     => 4,
            "order"         => 6,
            "can_switch"    => true, 
            $list           => $ct->get_plan_list($basic_year_v4, $bucket),
            $list_contact   => MAX_LIST_CONTACT,
            $search         => $ct->get_plan_search($basic_year_v4, $bucket),
            $contact        => $ct->get_bucket_credit($basic_year_v4, $bucket),
            $export         => true,
            $search_result  => $ct->get_plan_search_result($basic_year_v4, $bucket),
            $has_video      => true,
            $has_guest      => true,
            $has_sponser    => true,
            "locked_filters"    => [],
        ];
        
        ##################
        
        if($sort == 1):
            usort($plans, function($a, $b) {
                return $a["order"] <=> $b["order"];
            });   
        endif;
        $plans = array_column($plans, null, "plan_pref"); 
        return $plans;
    } 
endif;

if(!function_exists("is_higher_plan")):
    function is_higher_plan($plan_id, $auto_id, $payment = []) {
        $plans = plans($auto_id, 0, 0, LATEST_PLAN_BUCKET, $payment);
        $max = array_column($plans, 'plan_pref');
        arsort($max);
        $key =  array_key_first($max);        
        $higher_plan_key = $max[$key];        
        $is_higher_plan = $plan_id == $higher_plan_key ? true : false;        
        return $is_higher_plan;
    }
endif;

if(!function_exists("plan_amount")):
    function plan_amount($plan, $format = 1) {        
        $amount = $plan["cycle"] == "year" ? $plan["amount"] / 12  : $plan["amount"];
        $amount = number_format((float)$amount, 2, '.', '') + 0;
        $amount = $format ? $plan["symbol"].$amount : $amount;
        return $amount;
    }
endif;

if(!function_exists("formatAmount")):
    function formatAmount($amount, $pricing_page = 0) {
        $amount = $pricing_page ? ceil($amount) : number_format((float)$amount, 2, '.', '') + 0;
        return $amount;
    }
endif;

if(!function_exists('get_client_ip')):
    function get_client_ip() {
        $ip_addr = "";
        if (isset($_SERVER['HTTP_CLIENT_IP'])):
            $ip_addr = $_SERVER['HTTP_CLIENT_IP'];
        elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])):
            $ip_addr = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip_add_arr = explode(',', $ip_addr);
            foreach($ip_add_arr as $ip):
                if(!empty($ip)): $ip_addr = $ip; break; endif;
            endforeach;
        elseif(isset($_SERVER['HTTP_X_FORWARDED'])):
            $ip_addr = $_SERVER['HTTP_X_FORWARDED'];
        elseif(isset($_SERVER['HTTP_FORWARDED_FOR'])):
            $ip_addr = $_SERVER['HTTP_FORWARDED_FOR'];
        elseif(isset($_SERVER['HTTP_FORWARDED'])):
            $ip_addr = $_SERVER['HTTP_FORWARDED'];
        elseif(isset($_SERVER['REMOTE_ADDR'])):
            $ip_addr = $_SERVER['REMOTE_ADDR'];
        endif;
        
        return $ip_addr;
    }
endif;

if(!function_exists("include_with_variables")):
    function include_with_variables($filePath, $variables = []) {
        $output = "";
        if(file_exists($filePath)):
            extract($variables);
            
            ob_start();
            include $filePath;
            $output = ob_get_clean();
        endif;
        
        return $output;
    }
endif;

if(!function_exists("generate_otp")):
    function generate_otp($digits = 4) {
        $generator = "1357902468";
        $otp = "";
        for($i = 1; $i <= $digits; $i++):
            $otp .= substr($generator, (rand()%(strlen($generator))), 1);
        endfor;
        return $otp;
    }
endif;

if(!function_exists("get_locations")):
    function get_locations($string, $mode = 0) {
        $arr  = explode("|", $string);
        $location = [];
        foreach($arr as $loc):
            $loc_arr = explode(":", $loc);
            if($loc_arr[0] == "co"):
                $location["country"] = $loc_arr[1];
            elseif($loc_arr[0] == "re"):
                $location["region"] = $loc_arr[1];
            elseif($loc_arr[0] == "ci"):
                $location["city"] = $loc_arr[1];
            endif;
        endforeach;
        
        if($mode == 1):
            $location = implode(", ", array_map('ucwords', $location));
        endif;    
        
        return $location;
    }
endif;

if(!function_exists("get_packages")):
    function get_packages($query_package_id = "", $return_package_by_id = 0, $us_region = 0) {
        $packages = [
            [
                "id"    => "p:1",
                "name"  => "Asia",
                "type"  => 1,
                "data"  => '[{"coi":1},{"coi":12},{"coi":16},{"coi":18},{"coi":19},{"coi":26},{"coi":250},{"coi":39},{"coi":46},{"coi":57},{"coi":80},{"coi":100},{"coi":101},{"coi":102},{"coi":103},{"coi":106},{"coi":109},{"coi":111},{"coi":112},{"coi":115},{"coi":116},{"coi":117},{"coi":119},{"coi":129},{"coi":130},{"coi":142},{"coi":147},{"coi":150},{"coi":159},{"coi":163},{"coi":164},{"coi":166},{"coi":171},{"coi":176},{"coi":179},{"coi":190},{"coi":195},{"coi":202},{"coi":205},{"coi":210},{"coi":211},{"coi":212},{"coi":214},{"coi":215},{"coi":221},{"coi":222},{"coi":227},{"coi":232},{"coi":239}]'
            ],
            [
                "id"    => "p:2",
                "name"  => "Europe",
                "type"  => 1,
                "data"  => '[{"coi":3},{"coi":6},{"coi":12},{"coi":15},{"coi":16},{"coi":21},{"coi":22},{"coi":29},{"coi":35},{"coi":54},{"coi":57},{"coi":58},{"coi":59},{"coi":68},{"coi":74},{"coi":75},{"coi":80},{"coi":81},{"coi":84},{"coi":98},{"coi":99},{"coi":104},{"coi":107},{"coi":112},{"coi":118},{"coi":123},{"coi":124},{"coi":125},{"coi":132},{"coi":140},{"coi":141},{"coi":143},{"coi":151},{"coi":160},{"coi":162},{"coi":173},{"coi":174},{"coi":178},{"coi":179},{"coi":188},{"coi":192},{"coi":197},{"coi":198},{"coi":204},{"coi":208},{"coi":209},{"coi":221},{"coi":226},{"coi":228},{"coi":251}]'
            ],
            [
                "id"    => "p:3",
                "name"  => "Africa",
                "type"  => 1,
                "data"  => '[{"coi":4},{"coi":7},{"coi":24},{"coi":30},{"coi":36},{"coi":37},{"coi":38},{"coi":40},{"coi":43},{"coi":44},{"coi":49},{"coi":50},{"coi":60},{"coi":64},{"coi":66},{"coi":67},{"coi":69},{"coi":70},{"coi":78},{"coi":79},{"coi":82},{"coi":91},{"coi":92},{"coi":53},{"coi":113},{"coi":120},{"coi":121},{"coi":122},{"coi":127},{"coi":128},{"coi":131},{"coi":135},{"coi":136},{"coi":145},{"coi":146},{"coi":148},{"coi":155},{"coi":156},{"coi":180},{"coi":189},{"coi":191},{"coi":193},{"coi":194},{"coi":200},{"coi":201},{"coi":203},{"coi":206},{"coi":213},{"coi":216},{"coi":220},{"coi":225},{"coi":240},{"coi":241}]'
            ],
            [
                "id"    => "p:4",
                "name"  => "South America",
                "type"  => 1,
                "data"  => '[{"coi":11},{"coi":27},{"coi":32},{"coi":45},{"coi":48},{"coi":63},{"coi":93},{"coi":169},{"coi":170},{"coi":207},{"coi":231},{"coi":234}]'
            ],
            [
                "id"    => "p:5",
                "name"  => "Gulf Cooperation Council (GCC)",
                "type"  => 1,
                "data"  => '[{"coi":18},{"coi":115},{"coi":163},{"coi":176},{"coi":190},{"coi":227}]'
            ],
            [
                "id"    => "p:6",
                "name"  => "Asia-Pacific (APAC)",
                "type"  => 1,
                "data"  => '[{"coi":1},{"coi":14},{"coi":19},{"coi":26},{"coi":250},{"coi":39},{"coi":46},{"coi":73},{"coi":80},{"coi":97},{"coi":100},{"coi":101},{"coi":109},{"coi":112},{"coi":114},{"coi":115},{"coi":116},{"coi":117},{"coi":129},{"coi":130},{"coi":133},{"coi":139},{"coi":142},{"coi":147},{"coi":150},{"coi":153},{"coi":159},{"coi":165},{"coi":168},{"coi":171},{"coi":187},{"coi":195},{"coi":202},{"coi":205},{"coi":210},{"coi":211},{"coi":212},{"coi":214},{"coi":215},{"coi":218},{"coi":222},{"coi":224},{"coi":232},{"coi":233},{"coi":235},{"coi":239}]'
            ],
            [
                "id"    => "p:7",
                "name"  => "Middle East and North Africa (MENA)",
                "type"  => 1,
                "data"  => '[{"coi":4},{"coi":18},{"coi":64},{"coi":102},{"coi":103},{"coi":106},{"coi":111},{"coi":115},{"coi":119},{"coi":122},{"coi":145},{"coi":163},{"coi":166},{"coi":176},{"coi":190},{"coi":210},{"coi":220},{"coi":227},{"coi":239}]'
            ],
            [
                "id"    => "p:8",
                "name"  => "Southeast Asia (SEA)",
                "type"  => 1,
                "data"  => '[{"coi":250},{"coi":39},{"coi":101},{"coi":117},{"coi":129},{"coi":147},{"coi":171},{"coi":195},{"coi":214},{"coi":215},{"coi":235}]'
            ],
            [
                "id"    => "p:9",
                "name"  => "North America",
                "type"  => 1,
                "data"  => '[{"coi":41},{"coi":138},{"coi":229}]'
            ],
            [
                "id"    => "p:10",
                "name"  => "Central America",
                "type"  => 1,
                "data"  => '[{"coi":23},{"coi":52},{"coi":65},{"coi":89},{"coi":96},{"coi":154},{"coi":167}]'
            ],
            [
                "id"    => "p:11",
                "name"  => "Middle East",
                "type"  => 1,
                "data"  => '[{"coi":18},{"coi":57},{"coi":64},{"coi":102},{"coi":103},{"coi":106},{"coi":111},{"coi":115},{"coi":119},{"coi":163},{"coi":166},{"coi":176},{"coi":190},{"coi":210},{"coi":221},{"coi":227},{"coi":239}]'
            ],
            [
                "id"    => "p:12",
                "name"  => "Latin America",
                "type"  => 1,
                "data"  => '[{"coi":138},{"coi":23},{"coi":52},{"coi":65},{"coi":89},{"coi":96},{"coi":154},{"coi":167},{"coi":11},{"coi":27},{"coi":32},{"coi":45},{"coi":48},{"coi":63},{"coi":93},{"coi":169},{"coi":170},{"coi":207},{"coi":231},{"coi":234},{"coi":10},{"coi":17},{"coi":20},{"coi":55},{"coi":61},{"coi":62},{"coi":86},{"coi":94},{"coi":108},{"coi":175},{"coi":182},{"coi":183},{"coi":186},{"coi":219}]'
            ],
            [
                "id"    => "p:13",
                "name"  => "Caribbean",
                "type"  => 1,
                "data"  => '[{"coi":10},{"coi":17},{"coi":20},{"coi":55},{"coi":61},{"coi":62},{"coi":86},{"coi":94},{"coi":108},{"coi":175},{"coi":182},{"coi":183},{"coi":186},{"coi":219}]'
            ],
            [
                "id"    => "p:14",
                "name"  => "Southeastern United States",
                "type"  => 1,
                "data"  => '[{"coi":229,"sti":450},{"coi":229,"sti":711},{"coi":229,"sti":500},{"coi":229,"sti":517},{"coi":229,"sti":689},{"coi":229,"sti":432},{"coi":229,"sti":691},{"coi":229,"sti":443},{"coi":229,"sti":710},{"coi":229,"sti":157},{"coi":229,"sti":462},{"coi":229,"sti":700},{"coi":229,"sti":490},{"coi":229,"sti":698},{"coi":229,"sti":296},{"coi":229,"sti":379},{"coi":229,"sti":414}]'
            ],
            [
                "id"    => "p:15",
                "name"  => "Southwestern United States",
                "type"  => 1,
                "data"  => '[{"coi":229,"sti":363},{"coi":229,"sti":142},{"coi":229,"sti":695},{"coi":229,"sti":492},{"coi":229,"sti":703},{"coi":229,"sti":296},{"coi":229,"sti":433},{"coi":229,"sti":422}]'
            ],
            [
                "id"    => "p:16",
                "name"  => "Midwestern United States",
                "type"  => 1,
                "data"  => '[{"coi":229,"sti":343},{"coi":229,"sti":699},{"coi":229,"sti":708},{"coi":229,"sti":1050},{"coi":229,"sti":704},{"coi":229,"sti":693},{"coi":229,"sti":157},{"coi":229,"sti":696},{"coi":229,"sti":1128},{"coi":229,"sti":426},{"coi":229,"sti":694},{"coi":229,"sti":701}]'
            ],
            [
                "id"    => "p:17",
                "name"  => "Pacific Northwest",
                "type"  => 1,
                "data"  => '[{"coi":229,"sti":1120},{"coi":229,"sti":469},{"coi":35,"sti":918},{"coi":229,"sti":477},{"coi":229,"sti":274},{"coi":41,"sti":374}]'
            ],
            [
                "id"    => "p:18",
                "name"  => "San Francisco Bay Area",
                "type"  => 2,
                "data"  => '[{"cid":6174,"coi":229,"sti":142},{"cid":6343,"coi":229,"sti":142},{"cid":2751,"coi":229,"sti":142},{"cid":10268,"coi":229,"sti":142},{"cid":8067,"coi":229,"sti":142},{"cid":10929,"coi":229,"sti":142},{"cid":6333,"coi":229,"sti":142},{"cid":6200,"coi":229,"sti":142},{"cid":6257,"coi":229,"sti":142},{"cid":6275,"coi":229,"sti":142}]'
            ],
            [
                "id"    => "p:19",
                "name"  => "Silicon Valley",
                "type"  => 1,
                "data"  => '[{"cid":2751,"coi":229,"sti":142},{"cid":6333,"coi":229,"sti":142},{"cid":6200,"coi":229,"sti":142},{"cid":418,"coi":229,"sti":142},{"cid":6275,"coi":229,"sti":142},{"cid":6170,"coi":229,"sti":142},{"cid":12259,"coi":229,"sti":142},{"cid":6257,"coi":229,"sti":142},{"cid":8067,"coi":229,"sti":142},{"cid":9937,"coi":229,"sti":422}]'
            ],
            [
                "id"    => "p:20",
                "name"  => "New York Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 2732, "coi": 229, "sti": 416}, {"cid": 6195, "coi": 229, "sti": 445}, {"cid": 6294, "coi": 229, "sti": 445}, {"cid": 6253, "coi": 229, "sti": 416}, {"cid": 11154, "coi": 229, "sti": 445}, {"cid": 10944, "coi": 229, "sti": 702}, {"cid": 6254, "coi": 229, "sti": 702}, {"cid": 11155, "coi": 229, "sti": 445}, {"cid": 10720, "coi": 229, "sti": 702}, {"cid": 11986, "coi": 229, "sti": 702}, {"cid": 10279, "coi": 229, "sti": 445}, {"cid": 14946, "coi": 229, "sti": 416}, {"cid": 6509, "coi": 229, "sti": 445}, {"cid": 36727, "coi": 229, "sti": 445}, {"cid": 14110, "coi": 229, "sti": 445}, {"cid": 11690, "coi": 229, "sti": 416}, {"cid": 9121, "coi": 229, "sti": 702}, {"cid": 9953, "coi": 229, "sti": 416}, {"cid": 14891, "coi": 229, "sti": 416}, {"cid": 14496, "coi": 229, "sti": 416}, {"cid": 38160, "coi": 229, "sti": 445}, {"cid": 67811, "coi": 229, "sti": 445}, {"cid": 10326, "coi": 229, "sti": 445}, {"cid": 13770, "coi": 229, "sti": 445}, {"cid": 15003, "coi": 229, "sti": 416}, {"cid": 15654, "coi": 229, "sti": 702}, {"cid": 15886, "coi": 229, "sti": 690}, {"cid": 10648, "coi": 229, "sti": 445}, {"cid": 11801, "coi": 229, "sti": 445}, {"cid": 14498, "coi": 229, "sti": 445}, {"cid": 14100, "coi": 229, "sti": 445}]'
            ],
            [
                "id"    => "p:21",
                "name"  => "Chicago Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 1753, "coi": 229, "sti": 343}, {"cid": 10282, "coi": 229, "sti": 343}, {"cid": 9887, "coi": 229, "sti": 343}, {"cid": 12341, "coi": 229, "sti": 343}, {"cid": 10185, "coi": 229, "sti": 343}, {"cid": 6311, "coi": 229, "sti": 343}, {"cid": 9893, "coi": 229, "sti": 343}, {"cid": 10371, "coi": 229, "sti": 343}, {"cid": 11340, "coi": 229, "sti": 343}, {"cid": 10283, "coi": 229, "sti": 699}, {"cid": 11360, "coi": 229, "sti": 699}, {"cid": 10821, "coi": 229, "sti": 699}, {"cid": 11082, "coi": 229, "sti": 701}]'
            ],
            [
                "id"    => "p:22",
                "name"  => "Dallas–Fort Worth metroplex",
                "type"  => 2,
                "data"  => '[{"cid": 2727, "coi": 229, "sti": 296}, {"cid": 6303, "coi": 229, "sti": 296}, {"cid": 10193, "coi": 229, "sti": 296}, {"cid": 6211, "coi": 229, "sti": 296}, {"cid": 6317, "coi": 229, "sti": 296}, {"cid": 11317, "coi": 229, "sti": 296}, {"cid": 9940, "coi": 229, "sti": 296}, {"cid": 6310, "coi": 229, "sti": 296}]'
            ],
            [
                "id"    => "p:23",
                "name"  => "Houston Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 10388, "coi": 229, "sti": 296}, {"cid": 10671, "coi": 229, "sti": 296}, {"cid": 23427, "coi": 229, "sti": 296}, {"cid": 10927, "coi": 229, "sti": 296}, {"cid": 11193, "coi": 229, "sti": 296}, {"cid": 62851, "coi": 229, "sti": 296}, {"cid": 10980, "coi": 229, "sti": 296}, {"cid": 26442, "coi": 229, "sti": 296}, {"cid": 80393, "coi": 229, "sti": 296}, {"cid": 36655, "coi": 229, "sti": 296}, {"cid": 60773, "coi": 229, "sti": 296}, {"cid": 32405, "coi": 229, "sti": 296}, {"cid": 11959, "coi": 229, "sti": 296}, {"cid": 26437, "coi": 229, "sti": 296}, {"cid": 11880, "coi": 229, "sti": 296}, {"cid": 65726, "coi": 229, "sti": 296}, {"cid": 105090, "coi": 229, "sti": 296}, {"cid": 83012, "coi": 229, "sti": 296}, {"cid": 81413, "coi": 229, "sti": 296}]'
            ],
            [
                "id"    => "p:24",
                "name"  => "Miami Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 2945, "coi": 229, "sti": 517}, {"cid": 10012, "coi": 229, "sti": 517}, {"cid": 9911, "coi": 229, "sti": 517}, {"cid": 9815, "coi": 229, "sti": 517}, {"cid": 9800, "coi": 229, "sti": 517}, {"cid": 9816, "coi": 229, "sti": 517}, {"cid": 10824, "coi": 229, "sti": 517}, {"cid": 11020, "coi": 229, "sti": 517}, {"cid": 10554, "coi": 229, "sti": 517}, {"cid": 9873, "coi": 229, "sti": 517}, {"cid": 10328, "coi": 229, "sti": 517}, {"cid": 10499, "coi": 229, "sti": 517}, {"cid": 10714, "coi": 229, "sti": 517}, {"cid": 9897, "coi": 229, "sti": 517}, {"cid": 10553, "coi": 229, "sti": 517}, {"cid": 39004, "coi": 229, "sti": 517}, {"cid": 9910, "coi": 229, "sti": 517}, {"cid": 11206, "coi": 229, "sti": 517}, {"cid": 10884, "coi": 229, "sti": 517}, {"cid": 13378, "coi": 229, "sti": 517}, {"cid": 9896, "coi": 229, "sti": 517}, {"cid": 13364, "coi": 229, "sti": 517}, {"cid": 9916, "coi": 229, "sti": 517}, {"cid": 11667, "coi": 229, "sti": 517}, {"cid": 10006, "coi": 229, "sti": 517}, {"cid": 13423, "coi": 229, "sti": 517}, {"cid": 96393, "coi": 229, "sti": 517}, {"cid": 10635, "coi": 229, "sti": 517}, {"cid": 10748, "coi": 229, "sti": 517}]'
            ],
            [
                "id"    => "p:25",
                "name"  => "Philadelphia Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6186, "coi": 229, "sti": 690}, {"cid": 9902, "coi": 229, "sti": 690}, {"cid": 10625, "coi": 229, "sti": 690}, {"cid": 6338, "coi": 229, "sti": 690}, {"cid": 12008, "coi": 229, "sti": 690}, {"cid": 37591, "coi": 229, "sti": 690}, {"cid": 14435, "coi": 229, "sti": 690}, {"cid": 13715, "coi": 229, "sti": 690}, {"cid": 11030, "coi": 229, "sti": 690}, {"cid": 38218, "coi": 229, "sti": 690}, {"cid": 23299, "coi": 229, "sti": 690}, {"cid": 13143, "coi": 229, "sti": 690}, {"cid": 23466, "coi": 229, "sti": 690}, {"cid": 11036, "coi": 229, "sti": 445}, {"cid": 12945, "coi": 229, "sti": 445}, {"cid": 10056, "coi": 229, "sti": 445}, {"cid": 11968, "coi": 229, "sti": 445}, {"cid": 11353, "coi": 229, "sti": 445}, {"cid": 12044, "coi": 229, "sti": 445}, {"cid": 11509, "coi": 229, "sti": 445}, {"cid": 41646, "coi": 229, "sti": 445}, {"cid": 24095, "coi": 229, "sti": 445}, {"cid": 83117, "coi": 229, "sti": 445}, {"cid": 11074, "coi": 229, "sti": 445}, {"cid": 42389, "coi": 229, "sti": 445}, {"cid": 12935, "coi": 229, "sti": 445}, {"cid": 13208, "coi": 229, "sti": 445}, {"cid": 2907, "coi": 229, "sti": 500}, {"cid": 12145, "coi": 229, "sti": 500}, {"cid": 10075, "coi": 229, "sti": 500}, {"cid": 11688, "coi": 229, "sti": 500}, {"cid": 6260, "coi": 229, "sti": 500}, {"cid": 15179, "coi": 229, "sti": 443}]'
            ],
            [
                "id"    => "p:26",
                "name"  => "Washington, D.C. Metro Area",
                "type"  => 2,
                "data"  => '[{"cid": 6175, "coi": 229, "sti": 692}, {"cid": 6186, "coi": 229, "sti": 690}, {"cid": 9902, "coi": 229, "sti": 690}, {"cid": 10625, "coi": 229, "sti": 690}, {"cid": 6338, "coi": 229, "sti": 690}, {"cid": 12008, "coi": 229, "sti": 690}, {"cid": 37591, "coi": 229, "sti": 690}, {"cid": 14435, "coi": 229, "sti": 690}, {"cid": 13715, "coi": 229, "sti": 690}, {"cid": 11030, "coi": 229, "sti": 690}, {"cid": 38218, "coi": 229, "sti": 690}, {"cid": 23299, "coi": 229, "sti": 690}, {"cid": 13143, "coi": 229, "sti": 690}, {"cid": 23466, "coi": 229, "sti": 690}, {"cid": 11036, "coi": 229, "sti": 445}, {"cid": 12945, "coi": 229, "sti": 445}, {"cid": 10056, "coi": 229, "sti": 445}, {"cid": 11968, "coi": 229, "sti": 445}, {"cid": 11353, "coi": 229, "sti": 445}, {"cid": 12044, "coi": 229, "sti": 445}, {"cid": 11509, "coi": 229, "sti": 445}, {"cid": 41646, "coi": 229, "sti": 445}, {"cid": 24095, "coi": 229, "sti": 445}, {"cid": 83117, "coi": 229, "sti": 445}, {"cid": 11074, "coi": 229, "sti": 445}, {"cid": 42389, "coi": 229, "sti": 445}, {"cid": 12935, "coi": 229, "sti": 445}, {"cid": 13208, "coi": 229, "sti": 445}, {"cid": 2907, "coi": 229, "sti": 500}, {"cid": 12145, "coi": 229, "sti": 500}, {"cid": 10075, "coi": 229, "sti": 500}, {"cid": 11688, "coi": 229, "sti": 500}, {"cid": 6260, "coi": 229, "sti": 500}, {"cid": 15179, "coi": 229, "sti": 443}]'
            ],
            [
                "id"    => "p:27",
                "name"  => "Atlanta Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6168, "coi": 229, "sti": 689}, {"cid": 10853, "coi": 229, "sti": 689}, {"cid": 9920, "coi": 229, "sti": 689}, {"cid": 9993, "coi": 229, "sti": 689}, {"cid": 10134, "coi": 229, "sti": 689}, {"cid": 9912, "coi": 229, "sti": 689}, {"cid": 10561, "coi": 229, "sti": 689}, {"cid": 10661, "coi": 229, "sti": 689}, {"cid": 96514, "coi": 229, "sti": 689}, {"cid": 11022, "coi": 229, "sti": 689}, {"cid": 10017, "coi": 229, "sti": 689}, {"cid": 11021, "coi": 229, "sti": 689}, {"cid": 10645, "coi": 229, "sti": 689}, {"cid": 10054, "coi": 229, "sti": 689}, {"cid": 10807, "coi": 229, "sti": 689}]'
            ],
            [
                "id"    => "p:28",
                "name"  => "Phoenix Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6184, "coi": 229, "sti": 363}, {"cid": 10218, "coi": 229, "sti": 363}, {"cid": 9879, "coi": 229, "sti": 363}, {"cid": 12979, "coi": 229, "sti": 363}, {"cid": 12978, "coi": 229, "sti": 363}, {"cid": 9823, "coi": 229, "sti": 363}, {"cid": 36982, "coi": 229, "sti": 363}, {"cid": 1773, "coi": 229, "sti": 363}, {"cid": 13817, "coi": 229, "sti": 363}, {"cid": 11227, "coi": 229, "sti": 363}, {"cid": 40834, "coi": 229, "sti": 363}, {"cid": 11810, "coi": 229, "sti": 363}, {"cid": 38286, "coi": 229, "sti": 363}, {"cid": 12340, "coi": 229, "sti": 363}, {"cid": 33650, "coi": 229, "sti": 363}, {"cid": 90355, "coi": 229, "sti": 363}, {"cid": 9895, "coi": 229, "sti": 363}, {"cid": 48700, "coi": 229, "sti": 363}, {"cid": 83408, "coi": 229, "sti": 363}, {"cid": 12083, "coi": 229, "sti": 363}, {"cid": 39110, "coi": 229, "sti": 363}, {"cid": 95845, "coi": 229, "sti": 363}, {"cid": 27000, "coi": 229, "sti": 363}, {"cid": 95843, "coi": 229, "sti": 363}, {"cid": 13680, "coi": 229, "sti": 363}]'
            ],
            [
                "id"    => "p:29",
                "name"  => "Seattle Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 928, "coi": 229, "sti": 274}, {"cid": 6337, "coi": 229, "sti": 274}, {"cid": 6215, "coi": 229, "sti": 274}, {"cid": 10576, "coi": 229, "sti": 274}, {"cid": 14195, "coi": 229, "sti": 274}, {"cid": 11185, "coi": 229, "sti": 274}, {"cid": 13155, "coi": 229, "sti": 274}, {"cid": 6288, "coi": 229, "sti": 274}, {"cid": 6357, "coi": 229, "sti": 274}, {"cid": 13937, "coi": 229, "sti": 274}, {"cid": 11625, "coi": 229, "sti": 274}, {"cid": 14201, "coi": 229, "sti": 274}, {"cid": 11199, "coi": 229, "sti": 274}, {"cid": 14227, "coi": 229, "sti": 274}, {"cid": 14207, "coi": 229, "sti": 274}, {"cid": 14202, "coi": 229, "sti": 274}, {"cid": 13905, "coi": 229, "sti": 274}, {"cid": 68947, "coi": 229, "sti": 274}, {"cid": 10800, "coi": 229, "sti": 274}, {"cid": 14200, "coi": 229, "sti": 274}, {"cid": 14206, "coi": 229, "sti": 274}, {"cid": 14213, "coi": 229, "sti": 274}, {"cid": 14215, "coi": 229, "sti": 274}, {"cid": 11710, "coi": 229, "sti": 274}, {"cid": 37471, "coi": 229, "sti": 274}, {"cid": 31581, "coi": 229, "sti": 274}, {"cid": 38333, "coi": 229, "sti": 274}, {"cid": 6193, "coi": 229, "sti": 274}, {"cid": 6286, "coi": 229, "sti": 274}, {"cid": 6287, "coi": 229, "sti": 274}, {"cid": 11735, "coi": 229, "sti": 274}, {"cid": 14203, "coi": 229, "sti": 274}, {"cid": 11625, "coi": 229, "sti": 274}, {"cid": 14214, "coi": 229, "sti": 274}, {"cid": 14194, "coi": 229, "sti": 274}, {"cid": 89691, "coi": 229, "sti": 274}, {"cid": 89682, "coi": 229, "sti": 274}, {"cid": 14193, "coi": 229, "sti": 274}, {"cid": 14199, "coi": 229, "sti": 274}, {"cid": 15066, "coi": 229, "sti": 274}, {"cid": 37719, "coi": 229, "sti": 274}, {"cid": 78425, "coi": 229, "sti": 274}, {"cid": 9944, "coi": 229, "sti": 274}, {"cid": 14212, "coi": 229, "sti": 274}, {"cid": 34053, "coi": 229, "sti": 274}, {"cid": 6285, "coi": 229, "sti": 274}, {"cid": 13301, "coi": 229, "sti": 274}, {"cid": 14205, "coi": 229, "sti": 274}, {"cid": 58025, "coi": 229, "sti": 274}, {"cid": 37720, "coi": 229, "sti": 274}, {"cid": 11643, "coi": 229, "sti": 274}, {"cid": 34083, "coi": 229, "sti": 274}]'
            ],
            [
                "id"    => "p:30",
                "name"  => "Boston Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6160, "coi": 229, "sti": 362}, {"cid": 6227, "coi": 229, "sti": 362}, {"cid": 6226, "coi": 229, "sti": 362}, {"cid": 10918, "coi": 229, "sti": 362}, {"cid": 12335, "coi": 229, "sti": 362}, {"cid": 10179, "coi": 229, "sti": 362}, {"cid": 12257, "coi": 229, "sti": 362}, {"cid": 36864, "coi": 229, "sti": 362}, {"cid": 6225, "coi": 229, "sti": 362}, {"cid": 10275, "coi": 229, "sti": 362}, {"cid": 14354, "coi": 229, "sti": 362}, {"cid": 80469, "coi": 229, "sti": 362}, {"cid": 36901, "coi": 229, "sti": 362}, {"cid": 36933, "coi": 229, "sti": 362}, {"cid": 65821, "coi": 229, "sti": 362}, {"cid": 10991, "coi": 229, "sti": 362}, {"cid": 99385, "coi": 229, "sti": 362}, {"cid": 12937, "coi": 229, "sti": 362}, {"cid": 14778, "coi": 229, "sti": 362}, {"cid": 12953, "coi": 229, "sti": 362}, {"cid": 10651, "coi": 229, "sti": 362}, {"cid": 39967, "coi": 229, "sti": 362}, {"cid": 6345, "coi": 229, "sti": 362}, {"cid": 37134, "coi": 229, "sti": 362}, {"cid": 11394, "coi": 229, "sti": 362}, {"cid": 6262, "coi": 229, "sti": 362}, {"cid": 6299, "coi": 229, "sti": 362}, {"cid": 6312, "coi": 229, "sti": 362}, {"cid": 23377, "coi": 229, "sti": 362}, {"cid": 10652, "coi": 229, "sti": 362}, {"cid": 12947, "coi": 229, "sti": 362}, {"cid": 6228, "coi": 229, "sti": 362}, {"cid": 6221, "coi": 229, "sti": 362}, {"cid": 24125, "coi": 229, "sti": 362}, {"cid": 80207, "coi": 229, "sti": 362}, {"cid": 36936, "coi": 229, "sti": 362}, {"cid": 37052, "coi": 229, "sti": 362}, {"cid": 38513, "coi": 229, "sti": 362}, {"cid": 6319, "coi": 229, "sti": 362}, {"cid": 15374, "coi": 229, "sti": 362}, {"cid": 6271, "coi": 229, "sti": 362}, {"cid": 10361, "coi": 229, "sti": 362}, {"cid": 33977, "coi": 229, "sti": 362}, {"cid": 9798, "coi": 229, "sti": 362}, {"cid": 14777, "coi": 229, "sti": 362}, {"cid": 14347, "coi": 229, "sti": 362}, {"cid": 6322, "coi": 229, "sti": 709}, {"cid": 10501, "coi": 229, "sti": 709}, {"cid": 12281, "coi": 229, "sti": 709}, {"cid": 12073, "coi": 229, "sti": 709}, {"cid": 37444, "coi": 229, "sti": 709}, {"cid": 41046, "coi": 229, "sti": 709}, {"cid": 40999, "coi": 229, "sti": 709}, {"cid": 10735, "coi": 229, "sti": 709}, {"cid": 38889, "coi": 229, "sti": 709}, {"cid": 68607, "coi": 229, "sti": 709}, {"cid": 10507, "coi": 229, "sti": 1125}, {"cid": 12955, "coi": 229, "sti": 1125}, {"cid": 11802, "coi": 229, "sti": 1125}, {"cid": 14419, "coi": 229, "sti": 1125}, {"cid": 10650, "coi": 229, "sti": 1125}, {"cid": 14026, "coi": 229, "sti": 1125}, {"cid": 12052, "coi": 229, "sti": 1125}, {"cid": 6229, "coi": 229, "sti": 362}, {"cid": 11499, "coi": 229, "sti": 362}, {"cid": 14059, "coi": 229, "sti": 362}, {"cid": 13522, "coi": 229, "sti": 362}]'
            ],
            [
                "id"    => "p:31",
                "name"  => "Detroit Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6206, "coi": 229, "sti": 704}, {"cid": 10330, "coi": 229, "sti": 704}, {"cid": 9918, "coi": 229, "sti": 704}, {"cid": 11540, "coi": 229, "sti": 704}, {"cid": 13235, "coi": 229, "sti": 704}, {"cid": 10750, "coi": 229, "sti": 704}, {"cid": 40914, "coi": 229, "sti": 704}, {"cid": 26605, "coi": 229, "sti": 704}, {"cid": 13436, "coi": 229, "sti": 704}, {"cid": 23087, "coi": 229, "sti": 704}, {"cid": 11068, "coi": 229, "sti": 704}, {"cid": 11896, "coi": 229, "sti": 704}, {"cid": 37443, "coi": 229, "sti": 704}, {"cid": 11050, "coi": 229, "sti": 704}, {"cid": 10368, "coi": 229, "sti": 704}, {"cid": 10704, "coi": 229, "sti": 704}, {"cid": 11214, "coi": 229, "sti": 704}, {"cid": 13394, "coi": 229, "sti": 704}, {"cid": 33455, "coi": 229, "sti": 704}, {"cid": 78479, "coi": 229, "sti": 704}, {"cid": 13387, "coi": 229, "sti": 704}, {"cid": 39752, "coi": 229, "sti": 704}, {"cid": 9807, "coi": 229, "sti": 704}, {"cid": 9829, "coi": 229, "sti": 704}, {"cid": 11016, "coi": 229, "sti": 704}, {"cid": 6331, "coi": 229, "sti": 704}, {"cid": 11126, "coi": 229, "sti": 704}, {"cid": 11134, "coi": 229, "sti": 704}, {"cid": 8116, "coi": 229, "sti": 704}, {"cid": 13458, "coi": 229, "sti": 704}, {"cid": 13366, "coi": 229, "sti": 704}, {"cid": 26495, "coi": 229, "sti": 704}, {"cid": 13725, "coi": 229, "sti": 704}, {"cid": 11760, "coi": 229, "sti": 704}, {"cid": 40607, "coi": 229, "sti": 704}, {"cid": 68854, "coi": 229, "sti": 704}, {"cid": 11850, "coi": 229, "sti": 704}, {"cid": 11072, "coi": 229, "sti": 704}, {"cid": 39970, "coi": 229, "sti": 704}, {"cid": 15612, "coi": 229, "sti": 704}, {"cid": 79841, "coi": 229, "sti": 704}, {"cid": 10982, "coi": 229, "sti": 704}, {"cid": 41797, "coi": 229, "sti": 704}, {"cid": 12291, "coi": 229, "sti": 704}, {"cid": 11215, "coi": 229, "sti": 704}, {"cid": 6318, "coi": 229, "sti": 704}, {"cid": 12235, "coi": 229, "sti": 704}, {"cid": 23392, "coi": 229, "sti": 704}, {"cid": 40573, "coi": 229, "sti": 704}, {"cid": 6329, "coi": 229, "sti": 704}, {"cid": 6245, "coi": 229, "sti": 704}, {"cid": 76801, "coi": 229, "sti": 704}]'
            ],
            [
                "id"    => "p:32",
                "name"  => "Minneapolis–Saint Paul",
                "type"  => 2,
                "data"  => '[{"cid": 6178, "coi": 229, "sti": 693}, {"cid": 11209, "coi": 229, "sti": 693}, {"cid": 6219, "coi": 229, "sti": 693}, {"cid": 9853, "coi": 229, "sti": 693}, {"cid": 13199, "coi": 229, "sti": 693}, {"cid": 9986, "coi": 229, "sti": 693}, {"cid": 11133, "coi": 229, "sti": 693}, {"cid": 13594, "coi": 229, "sti": 693}, {"cid": 15905, "coi": 229, "sti": 693}, {"cid": 6315, "coi": 229, "sti": 693}, {"cid": 15116, "coi": 229, "sti": 693}, {"cid": 15670, "coi": 229, "sti": 693}, {"cid": 33911, "coi": 229, "sti": 693}, {"cid": 12968, "coi": 229, "sti": 693}, {"cid": 34027, "coi": 229, "sti": 693}, {"cid": 12975, "coi": 229, "sti": 693}, {"cid": 23216, "coi": 229, "sti": 693}, {"cid": 15111, "coi": 229, "sti": 693}, {"cid": 15112, "coi": 229, "sti": 693}, {"cid": 62929, "coi": 229, "sti": 693}, {"cid": 14569, "coi": 229, "sti": 693}, {"cid": 15627, "coi": 229, "sti": 693}, {"cid": 12096, "coi": 229, "sti": 693}, {"cid": 15909, "coi": 229, "sti": 693}, {"cid": 39777, "coi": 229, "sti": 693}, {"cid": 30868, "coi": 229, "sti": 693}, {"cid": 34118, "coi": 229, "sti": 693}, {"cid": 67246, "coi": 229, "sti": 693}, {"cid": 15114, "coi": 229, "sti": 693}, {"cid": 33995, "coi": 229, "sti": 693}, {"cid": 15204, "coi": 229, "sti": 693}, {"cid": 36806, "coi": 229, "sti": 693}, {"cid": 99557, "coi": 229, "sti": 693}, {"cid": 36772, "coi": 229, "sti": 693}, {"cid": 66346, "coi": 229, "sti": 693}, {"cid": 37842, "coi": 229, "sti": 693}, {"cid": 10952, "coi": 229, "sti": 693}, {"cid": 13593, "coi": 229, "sti": 693}, {"cid": 15109, "coi": 229, "sti": 693}, {"cid": 13745, "coi": 229, "sti": 693}, {"cid": 34040, "coi": 229, "sti": 693}, {"cid": 33926, "coi": 229, "sti": 693}, {"cid": 13274, "coi": 229, "sti": 693}, {"cid": 37049, "coi": 229, "sti": 693}, {"cid": 42047, "coi": 229, "sti": 693}, {"cid": 36770, "coi": 229, "sti": 693}, {"cid": 26583, "coi": 229, "sti": 693}, {"cid": 99736, "coi": 229, "sti": 693}, {"cid": 66357, "coi": 229, "sti": 693}, {"cid": 99694, "coi": 229, "sti": 693}, {"cid": 89388, "coi": 229, "sti": 693}, {"cid": 37517, "coi": 229, "sti": 693}, {"cid": 30869, "coi": 229, "sti": 693}, {"cid": 13266, "coi": 229, "sti": 693}, {"cid": 79641, "coi": 229, "sti": 693}, {"cid": 41196, "coi": 229, "sti": 693}, {"cid": 26564, "coi": 229, "sti": 693}, {"cid": 89419, "coi": 229, "sti": 693}, {"cid": 15160, "coi": 229, "sti": 693}, {"cid": 99560, "coi": 229, "sti": 693}, {"cid": 60771, "coi": 229, "sti": 693}, {"cid": 84709, "coi": 229, "sti": 693}, {"cid": 105091, "coi": 229, "sti": 693}, {"cid": 99600, "coi": 229, "sti": 693}, {"cid": 99729, "coi": 229, "sti": 693}, {"cid": 89434, "coi": 229, "sti": 693}, {"cid": 99685, "coi": 229, "sti": 693}, {"cid": 15033, "coi": 229, "sti": 693}, {"cid": 99732, "coi": 229, "sti": 693}, {"cid": 99592, "coi": 229, "sti": 693}, {"cid": 79806, "coi": 229, "sti": 693}, {"cid": 79678, "coi": 229, "sti": 693}, {"cid": 67627, "coi": 229, "sti": 693}, {"cid": 99730, "coi": 229, "sti": 693}, {"cid": 40885, "coi": 229, "sti": 693}, {"cid": 23361, "coi": 229, "sti": 693}, {"cid": 94222, "coi": 229, "sti": 693}, {"cid": 84708, "coi": 229, "sti": 693}, {"cid": 13156, "coi": 229, "sti": 693}, {"cid": 14658, "coi": 229, "sti": 693}, {"cid": 99621, "coi": 229, "sti": 693}, {"cid": 99573, "coi": 229, "sti": 693}, {"cid": 99544, "coi": 229, "sti": 693}, {"cid": 68812, "coi": 229, "sti": 693}, {"cid": 15110, "coi": 229, "sti": 693}, {"cid": 99715, "coi": 229, "sti": 693}, {"cid": 99652, "coi": 229, "sti": 693}, {"cid": 99632, "coi": 229, "sti": 693}, {"cid": 40139, "coi": 229, "sti": 693}, {"cid": 99714, "coi": 229, "sti": 693}, {"cid": 38429, "coi": 229, "sti": 693}, {"cid": 33951, "coi": 229, "sti": 693}, {"cid": 15966, "coi": 229, "sti": 693}, {"cid": 15628, "coi": 229, "sti": 693}, {"cid": 38807, "coi": 229, "sti": 693}, {"cid": 38498, "coi": 229, "sti": 693}, {"cid": 99679, "coi": 229, "sti": 693}, {"cid": 67628, "coi": 229, "sti": 693}, {"cid": 67082, "coi": 229, "sti": 693}, {"cid": 79569, "coi": 229, "sti": 693}, {"cid": 105092, "coi": 229, "sti": 693}, {"cid": 11269, "coi": 229, "sti": 693}, {"cid": 26570, "coi": 229, "sti": 693}, {"cid": 13877, "coi": 229, "sti": 693}, {"cid": 80187, "coi": 229, "sti": 693}, {"cid": 11527, "coi": 229, "sti": 693}, {"cid": 37130, "coi": 229, "sti": 693}]'
            ],
            [
                "id"    => "p:33",
                "name"  => "San Diego Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6177, "coi": 229, "sti": 142}, {"cid": 10866, "coi": 229, "sti": 142}, {"cid": 9997, "coi": 229, "sti": 142}, {"cid": 10532, "coi": 229, "sti": 142}, {"cid": 11218, "coi": 229, "sti": 142}, {"cid": 10208, "coi": 229, "sti": 142}, {"cid": 11013, "coi": 229, "sti": 142}, {"cid": 9825, "coi": 229, "sti": 142}, {"cid": 11077, "coi": 229, "sti": 142}, {"cid": 12013, "coi": 229, "sti": 142}, {"cid": 10868, "coi": 229, "sti": 142}, {"cid": 12134, "coi": 229, "sti": 142}, {"cid": 37119, "coi": 229, "sti": 142}, {"cid": 60897, "coi": 229, "sti": 142}, {"cid": 12148, "coi": 229, "sti": 142}, {"cid": 13553, "coi": 229, "sti": 142}, {"cid": 40826, "coi": 229, "sti": 142}, {"cid": 37744, "coi": 229, "sti": 142}]'
            ],
            [
                "id"    => "p:34",
                "name"  => "Tampa Bay Area",
                "type"  => 2,
                "data"  => '[{"cid": 6205, "coi": 229, "sti": 517}, {"cid": 6290, "coi": 229, "sti": 517}, {"cid": 10019, "coi": 229, "sti": 517}, {"cid": 12919, "coi": 229, "sti": 517}, {"cid": 14942, "coi": 229, "sti": 517}, {"cid": 6289, "coi": 229, "sti": 517}, {"cid": 10551, "coi": 229, "sti": 517}, {"cid": 6273, "coi": 229, "sti": 517}, {"cid": 19708, "coi": 229, "sti": 517}, {"cid": 11816, "coi": 229, "sti": 517}, {"cid": 32443, "coi": 229, "sti": 517}, {"cid": 32441, "coi": 229, "sti": 517}, {"cid": 13658, "coi": 229, "sti": 517}, {"cid": 79854, "coi": 229, "sti": 517}, {"cid": 11782, "coi": 229, "sti": 517}, {"cid": 11905, "coi": 229, "sti": 517}, {"cid": 10788, "coi": 229, "sti": 517}, {"cid": 32439, "coi": 229, "sti": 517}, {"cid": 14827, "coi": 229, "sti": 517}, {"cid": 9927, "coi": 229, "sti": 517}]'
            ],
            [
                "id"    => "p:35",
                "name"  => "Denver Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6181, "coi": 229, "sti": 695}, {"cid": 10213, "coi": 229, "sti": 695}, {"cid": 9945, "coi": 229, "sti": 695}, {"cid": 9922, "coi": 229, "sti": 695}, {"cid": 13558, "coi": 229, "sti": 695}, {"cid": 13537, "coi": 229, "sti": 695}, {"cid": 11703, "coi": 229, "sti": 695}, {"cid": 10889, "coi": 229, "sti": 695}, {"cid": 13145, "coi": 229, "sti": 695}, {"cid": 11611, "coi": 229, "sti": 695}, {"cid": 14151, "coi": 229, "sti": 695}, {"cid": 12969, "coi": 229, "sti": 695}, {"cid": 9864, "coi": 229, "sti": 695}, {"cid": 13868, "coi": 229, "sti": 695}, {"cid": 96283, "coi": 229, "sti": 695}, {"cid": 11138, "coi": 229, "sti": 695}, {"cid": 12976, "coi": 229, "sti": 695}, {"cid": 10062, "coi": 229, "sti": 695}, {"cid": 10105, "coi": 229, "sti": 695}, {"cid": 13757, "coi": 229, "sti": 695}, {"cid": 83039, "coi": 229, "sti": 695}, {"cid": 37577, "coi": 229, "sti": 695}, {"cid": 96278, "coi": 229, "sti": 695}]'
            ],
            [
                "id"    => "p:36",
                "name"  => "Baltimore Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 2776, "coi": 229, "sti": 443}, {"cid": 10570, "coi": 229, "sti": 443}, {"cid": 12932, "coi": 229, "sti": 443}, {"cid": 11786, "coi": 229, "sti": 443}, {"cid": 82058, "coi": 229, "sti": 443}, {"cid": 13908, "coi": 229, "sti": 443}, {"cid": 33921, "coi": 229, "sti": 443}, {"cid": 33918, "coi": 229, "sti": 443}, {"cid": 80092, "coi": 229, "sti": 443}, {"cid": 15181, "coi": 229, "sti": 443}, {"cid": 15176, "coi": 229, "sti": 443}, {"cid": 78744, "coi": 229, "sti": 443}, {"cid": 13238, "coi": 229, "sti": 443}, {"cid": 36705, "coi": 229, "sti": 443}, {"cid": 9921, "coi": 229, "sti": 443}, {"cid": 23430, "coi": 229, "sti": 443}, {"cid": 11484, "coi": 229, "sti": 443}]'
            ],
            [
                "id"    => "p:37",
                "name"  => "St. Louis Metropolitan Area",
                "type"  => 2,
                "data"  => '[{"cid": 6189, "coi": 229, "sti": 157}, {"cid": 10586, "coi": 229, "sti": 157}, {"cid": 10984, "coi": 229, "sti": 157}, {"cid": 10540, "coi": 229, "sti": 157}, {"cid": 10602, "coi": 229, "sti": 157}, {"cid": 10959, "coi": 229, "sti": 343}, {"cid": 10790, "coi": 229, "sti": 343}, {"cid": 10912, "coi": 229, "sti": 343}, {"cid": 13404, "coi": 229, "sti": 343}, {"cid": 11141, "coi": 229, "sti": 343}, {"cid": 36690, "coi": 229, "sti": 157}, {"cid": 26476, "coi": 229, "sti": 343}, {"cid": 10533, "coi": 229, "sti": 157}, {"cid": 13894, "coi": 229, "sti": 157}, {"cid": 13391, "coi": 229, "sti": 157}, {"cid": 11951, "coi": 229, "sti": 157}, {"cid": 11923, "coi": 229, "sti": 157}, {"cid": 10498, "coi": 229, "sti": 343}]'
            ],
            [
                "id"    => "p:38",
                "name"  => "Greater Los Angeles",
                "type"  => 2,
                "data"  => '[{"cid": 10947, "coi": 229, "sti": 142}, {"cid": 11037, "coi": 229, "sti": 142}, {"cid": 12206, "coi": 229, "sti": 142}, {"cid": 40987, "coi": 229, "sti": 142}, {"cid": 78414, "coi": 229, "sti": 142}, {"cid": 12336, "coi": 229, "sti": 142}, {"cid": 11884, "coi": 229, "sti": 142}, {"cid": 40844, "coi": 229, "sti": 142}, {"cid": 81980, "coi": 229, "sti": 142}, {"cid": 14014, "coi": 229, "sti": 142}, {"cid": 10503, "coi": 229, "sti": 142}, {"cid": 88710, "coi": 229, "sti": 142}, {"cid": 9978, "coi": 229, "sti": 142}, {"cid": 10100, "coi": 229, "sti": 142}, {"cid": 10298, "coi": 229, "sti": 142}, {"cid": 11631, "coi": 229, "sti": 142}, {"cid": 6242, "coi": 229, "sti": 142}, {"cid": 6239, "coi": 229, "sti": 142}, {"cid": 10678, "coi": 229, "sti": 142}, {"cid": 13540, "coi": 229, "sti": 142}, {"cid": 84355, "coi": 229, "sti": 142}, {"cid": 10047, "coi": 229, "sti": 142}, {"cid": 14056, "coi": 229, "sti": 142}, {"cid": 10622, "coi": 229, "sti": 142}, {"cid": 39507, "coi": 229, "sti": 142}, {"cid": 10400, "coi": 229, "sti": 142}, {"cid": 6320, "coi": 229, "sti": 142}, {"cid": 11221, "coi": 229, "sti": 142}, {"cid": 11629, "coi": 229, "sti": 142}, {"cid": 14015, "coi": 229, "sti": 142}, {"cid": 83662, "coi": 229, "sti": 142}, {"cid": 13477, "coi": 229, "sti": 142}, {"cid": 14021, "coi": 229, "sti": 142}, {"cid": 65834, "coi": 229, "sti": 142}, {"cid": 11767, "coi": 229, "sti": 142}, {"cid": 6249, "coi": 229, "sti": 142}, {"cid": 10041, "coi": 229, "sti": 142}, {"cid": 38533, "coi": 229, "sti": 142}, {"cid": 32482, "coi": 229, "sti": 142}, {"cid": 88742, "coi": 229, "sti": 142}, {"cid": 14023, "coi": 229, "sti": 142}, {"cid": 32398, "coi": 229, "sti": 142}, {"cid": 37132, "coi": 229, "sti": 142}, {"cid": 11632, "coi": 229, "sti": 142}, {"cid": 10592, "coi": 229, "sti": 142}, {"cid": 39323, "coi": 229, "sti": 142}, {"cid": 37741, "coi": 229, "sti": 142}, {"cid": 9822, "coi": 229, "sti": 142}, {"cid": 2780, "coi": 229, "sti": 142}, {"cid": 68624, "coi": 229, "sti": 142}, {"cid": 11728, "coi": 229, "sti": 142}, {"cid": 12972, "coi": 229, "sti": 142}, {"cid": 42156, "coi": 229, "sti": 142}, {"cid": 13791, "coi": 229, "sti": 142}, {"cid": 11444, "coi": 229, "sti": 142}, {"cid": 9996, "coi": 229, "sti": 142}, {"cid": 15716, "coi": 229, "sti": 142}, {"cid": 11175, "coi": 229, "sti": 142}, {"cid": 76834, "coi": 229, "sti": 142}, {"cid": 40846, "coi": 229, "sti": 142}, {"cid": 6332, "coi": 229, "sti": 142}, {"cid": 36879, "coi": 229, "sti": 142}, {"cid": 6277, "coi": 229, "sti": 142}, {"cid": 11265, "coi": 229, "sti": 142}, {"cid": 23418, "coi": 229, "sti": 142}, {"cid": 96173, "coi": 229, "sti": 142}, {"cid": 32387, "coi": 229, "sti": 142}, {"cid": 9845, "coi": 229, "sti": 142}, {"cid": 6325, "coi": 229, "sti": 142}, {"cid": 13777, "coi": 229, "sti": 142}, {"cid": 11765, "coi": 229, "sti": 142}, {"cid": 37050, "coi": 229, "sti": 142}, {"cid": 10773, "coi": 229, "sti": 142}, {"cid": 11695, "coi": 229, "sti": 142}, {"cid": 11158, "coi": 229, "sti": 142}, {"cid": 66860, "coi": 229, "sti": 142}, {"cid": 80084, "coi": 229, "sti": 142}, {"cid": 39463, "coi": 229, "sti": 142}, {"cid": 10591, "coi": 229, "sti": 142}, {"cid": 36867, "coi": 229, "sti": 142}, {"cid": 78564, "coi": 229, "sti": 142}, {"cid": 9818, "coi": 229, "sti": 142}, {"cid": 96176, "coi": 229, "sti": 142}, {"cid": 6347, "coi": 229, "sti": 142}, {"cid": 15609, "coi": 229, "sti": 142}, {"cid": 14068, "coi": 229, "sti": 142}, {"cid": 9899, "coi": 229, "sti": 142}, {"cid": 11159, "coi": 229, "sti": 142}, {"cid": 11014, "coi": 229, "sti": 142}, {"cid": 9947, "coi": 229, "sti": 142}, {"cid": 6282, "coi": 229, "sti": 142}, {"cid": 10511, "coi": 229, "sti": 142}, {"cid": 12238, "coi": 229, "sti": 142}, {"cid": 11094, "coi": 229, "sti": 142}, {"cid": 11533, "coi": 229, "sti": 142}, {"cid": 12236, "coi": 229, "sti": 142}, {"cid": 10299, "coi": 229, "sti": 142}, {"cid": 11778, "coi": 229, "sti": 142}, {"cid": 10849, "coi": 229, "sti": 142}, {"cid": 6279, "coi": 229, "sti": 142}, {"cid": 10599, "coi": 229, "sti": 142}, {"cid": 11264, "coi": 229, "sti": 142}, {"cid": 62926, "coi": 229, "sti": 142}, {"cid": 37109, "coi": 229, "sti": 142}, {"cid": 10908, "coi": 229, "sti": 142}, {"cid": 40585, "coi": 229, "sti": 142}, {"cid": 10597, "coi": 229, "sti": 142}, {"cid": 13323, "coi": 229, "sti": 142}, {"cid": 10596, "coi": 229, "sti": 142}, {"cid": 10063, "coi": 229, "sti": 142}, {"cid": 9843, "coi": 229, "sti": 142}, {"cid": 10989, "coi": 229, "sti": 142}, {"cid": 9844, "coi": 229, "sti": 142}, {"cid": 11106, "coi": 229, "sti": 142}, {"cid": 37036, "coi": 229, "sti": 142}, {"cid": 10937, "coi": 229, "sti": 142}, {"cid": 11100, "coi": 229, "sti": 142}, {"cid": 40277, "coi": 229, "sti": 142}, {"cid": 6201, "coi": 229, "sti": 142}, {"cid": 78365, "coi": 229, "sti": 142}, {"cid": 36885, "coi": 229, "sti": 142}, {"cid": 10902, "coi": 229, "sti": 142}, {"cid": 32385, "coi": 229, "sti": 142}, {"cid": 26561, "coi": 229, "sti": 142}, {"cid": 6346, "coi": 229, "sti": 142}, {"cid": 88736, "coi": 229, "sti": 142}, {"cid": 88739, "coi": 229, "sti": 142}, {"cid": 32382, "coi": 229, "sti": 142}, {"cid": 37813, "coi": 229, "sti": 142}, {"cid": 10436, "coi": 229, "sti": 142}, {"cid": 14020, "coi": 229, "sti": 142}, {"cid": 10594, "coi": 229, "sti": 142}, {"cid": 14495, "coi": 229, "sti": 142}, {"cid": 9931, "coi": 229, "sti": 142}, {"cid": 13426, "coi": 229, "sti": 142}, {"cid": 32383, "coi": 229, "sti": 142}, {"cid": 14239, "coi": 229, "sti": 142}, {"cid": 39230, "coi": 229, "sti": 142}, {"cid": 9960, "coi": 229, "sti": 142}, {"cid": 10564, "coi": 229, "sti": 142}, {"cid": 11099, "coi": 229, "sti": 142}, {"cid": 9817, "coi": 229, "sti": 142}, {"cid": 10086, "coi": 229, "sti": 142}, {"cid": 14016, "coi": 229, "sti": 142}, {"cid": 23341, "coi": 229, "sti": 142}, {"cid": 6348, "coi": 229, "sti": 142}, {"cid": 10593, "coi": 229, "sti": 142}, {"cid": 6281, "coi": 229, "sti": 142}, {"cid": 36869, "coi": 229, "sti": 142}, {"cid": 32388, "coi": 229, "sti": 142}, {"cid": 23321, "coi": 229, "sti": 142}, {"cid": 12056, "coi": 229, "sti": 142}, {"cid": 15715, "coi": 229, "sti": 142}, {"cid": 13763, "coi": 229, "sti": 142}, {"cid": 76851, "coi": 229, "sti": 142}, {"cid": 38579, "coi": 229, "sti": 142}, {"cid": 14018, "coi": 229, "sti": 142}, {"cid": 70016, "coi": 229, "sti": 142}, {"cid": 15629, "coi": 229, "sti": 142}, {"cid": 11599, "coi": 229, "sti": 142}, {"cid": 36878, "coi": 229, "sti": 142}, {"cid": 11768, "coi": 229, "sti": 142}, {"cid": 13607, "coi": 229, "sti": 142}, {"cid": 10122, "coi": 229, "sti": 142}, {"cid": 10523, "coi": 229, "sti": 142}, {"cid": 23383, "coi": 229, "sti": 142}, {"cid": 11065, "coi": 229, "sti": 142}, {"cid": 9837, "coi": 229, "sti": 142}, {"cid": 32381, "coi": 229, "sti": 142}, {"cid": 13490, "coi": 229, "sti": 142}, {"cid": 15630, "coi": 229, "sti": 142}, {"cid": 12007, "coi": 229, "sti": 142}, {"cid": 11172, "coi": 229, "sti": 142}, {"cid": 84153, "coi": 229, "sti": 142}, {"cid": 10672, "coi": 229, "sti": 142}, {"cid": 6251, "coi": 229, "sti": 142}, {"cid": 13456, "coi": 229, "sti": 142}, {"cid": 66881, "coi": 229, "sti": 142}, {"cid": 6241, "coi": 229, "sti": 142}, {"cid": 32386, "coi": 229, "sti": 142}, {"cid": 10643, "coi": 229, "sti": 142}, {"cid": 10557, "coi": 229, "sti": 142}]'
            ],            
        ];
        
        if($us_region):
            $packages = array_filter($packages, function($item) {
                return $item["type"] !== 1;
            });
            $packages = array_values($packages);
        endif;
        
        $packages = array_column($packages, null, "id");        
        if($return_package_by_id):
            $package = isset($packages[$query_package_id]) ? $packages[$query_package_id] : [];
            return $package;
        endif;
        
        if(!empty($query_package_id)):
            /*$query_package_id = '~'.$query_package_id.'~i';
            $packages = array_filter($packages, function($item) use ($query_package_id) {
                return preg_match($query_package_id, $item["name"]);
            });*/
        
            $escaped = preg_quote($query_package_id, '~');
            $pattern = '~^' . $escaped . '~i';
            $packages = array_filter($packages, function($item) use ($pattern) {
                return preg_match($pattern, $item["name"]);
            });            
            
        endif;
        return $packages;        
    }
endif;

if(!function_exists("generate_delete_acc_email")):
    function generate_delete_acc_email($email) {
        return $email."_".time()."_deleted";
    }
endif;

if(!function_exists("is_valid_date")):
    function is_valid_date($date) {
        list($year, $month, $day) = explode('-', $date);
        $year = (int)$year;
        $month = (int)$month;
        $day = (int)$day;    
        return checkdate($month, $day, $year);
    }
endif;

if(!function_exists("get_billing_date")):
    function get_billing_date($input_date) {
        if(empty($input_date)):
            return false;
        endif;
        
        $current_year = date("Y");
        $start_date = date("Y-m-d", strtotime("$current_year-" . date("m-d", strtotime($input_date))));
        $end_date = date("Y-m-d", strtotime("-1 day", strtotime("+1 month", strtotime($start_date))));
        return [
            'start' => $start_date,
            'end'   => $end_date
        ];
    }
endif;

if(!function_exists("get_next_release_date")):
    function get_next_release_date($billing_date) {   
        
        if(empty($billing_date)):        
            return NULL;
        endif;
        
        list($year, $month, $day) = explode('-', $billing_date);
        $month += 1;        
        if($month > 12):
            $month = 1; $year += 1;
        endif;
        
        $month = str_pad($month, 2, "0", STR_PAD_LEFT);
        $cycle_date = date("$year-$month-$day");
        $next_date = is_valid_date($cycle_date) ? $cycle_date : date("Y-m-t", strtotime("$year-$month"));
        return $next_date;
    }
endif;

if(!function_exists("get_pending_month")):
    function get_pending_month($billing_date, $release_date, $credits_month, $current_date = "") {
        $current_date = !empty($current_date) ? $current_date : date("Y-m-d");
        $credits = $pending_month = 0;
        
        if($current_date > $release_date):            
            $ts1 = strtotime($billing_date);
            $ts2 = strtotime($current_date);
            
            $year1 = date('Y', $ts1); 
            $year2 = date('Y', $ts2);
            
            $month1 = date('m', $ts1);
            $month2 = date('m', $ts2);
            
            $day1 = date('d', $ts1);
            $day2 = date('d', $ts2);
            
            $total_month = (($year2 - $year1) * 12) + ($month2 - $month1);
            $total_month = $day2 < $day1 ? $total_month - 1 : $total_month;
            
            $pending_month = $total_month % 12;
            //$pending_month += 1;  
            $credits = $pending_month > 0 ? $pending_month * $credits_month : 0;
        endif;
        
        $response = [
            "month"     => $pending_month,
            "credits"   => $credits,
            "total"     => $total_month,
            "next_date" => date("Y-m-d", strtotime("+$total_month months ".$billing_date))
        ];
        
        return $response;
    }
endif;


function double_escape($string) {
    return addslashes(addslashes($string)); 
}

function contains_spcial_char($string) {
    /*if(strpos($string, '&') !== false):
        return true;
    endif;
    return false;*/
    //return preg_match('/[^a-zA-Z0-9 ]/', $string);
    
    
    return preg_match("/[^a-zA-Z0-9 ]+/", $string);
    //return preg_match("/[^a-zA-Z0-9' –—,\.]/", $string) === 1;
}

if(!function_exists("sanitize_query")):
    function sanitize_query($query, $mode = 0) {
        //$query = filter_var($query, FILTER_SANITIZE_STRING);
        
        if($mode == 1):
            $query = str_replace("&", "__AAMPP__", $query);
        endif;
        
        if(!empty($mode)):
            $query = str_replace(["–", "–—", ",", ".", "'"], ["__EN__", "__DA__", "__COM__", "__DOT__", "__SIN__"], $query);
        endif;
        
        $query = strip_ctrl_char($query);
        $query = escape_solr_reserved_chars($query);
        $query = htmlentities($query, ENT_NOQUOTES, "UTF-8");  
        
        if($mode == 1):
            $query = str_replace("__AAMPP__", "&", $query);
        endif;
        
        if(!empty($mode)):
            $query = str_replace(["__EN__", "__DA__", "__COM__", "__DOT__", "__SIN__"], ["–", "–—", ",", ".", "'"], $query);
        endif;
        
        return $query;
    }
endif;

if(!function_exists("strip_ctrl_char")):
    function strip_ctrl_char($string) {
        return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $string);
    }
endif;

if(!function_exists("escape_solr_reserved_chars")):
    function escape_solr_reserved_chars($string) {
        $solr_reserved_chars = ['+' ,'-' ,'&&' ,'||' ,'!' ,'(' ,')' ,'{' ,'}' ,'[' ,']' ,'^' ,'"' ,'~' ,'*' ,'?' ,':' ,'/',"’"];
        $solr_reserved_chars_escaped = ['\+','\-','\&&','\||','\!','\(','\)','\{','\}','\[','\]','\^','\"','\~','\*','\?','\:','\/',"\'"];
        return str_replace($solr_reserved_chars, $solr_reserved_chars_escaped, $string);
    }
endif;

if(!function_exists("un_escape_solr_special_chars")):
    function un_escape_solr_special_chars($string) {
        
        if(empty($string)):
            return $string;
        endif;
        
        $solr_reserved_chars = array('+','-','&&','||','!','(',')','{','}','[',']','^','"','~','*','?',':');
        $solr_reserved_chars_escaped = array('\+','\-','\&&','\||','\!','\(','\)','\{','\}','\[','\]','\^','\"','\~','\*','\?','\:');
        return str_replace($solr_reserved_chars_escaped,$solr_reserved_chars,$string);
    }
endif;

if(!function_exists("solr_timestamp_to_rssdate")):
    function solr_timestamp_to_rssdate($timestamp) {
        $mysqltime = str_replace("Z","",str_replace("T","",$timestamp));
        return mysql_timestamp_rssdate($mysqltime);
    }
endif;

if(!function_exists("mysql_timestamp_rssdate")):
    function mysql_timestamp_rssdate($timestamp) {
        $year = (int)substr($timestamp, 0, 4);
        $month = (int)substr($timestamp, 5, 2);
        $day = (int)substr($timestamp, 8, 2);
        $hour = (int)substr($timestamp, 11, 2);
        $min = (int)substr($timestamp, 14, 2);
        $sec = (int)substr($timestamp, 17, 2);
        return date('Y-m-d H:i:s', mktime($hour, $min, $sec, $month, $day, $year));
    }
endif;

if(!function_exists("sanitize_solr_text")):
    function sanitize_solr_text($text) {
        if(empty($text)):
            return $text;
        endif;
        return html_entity_decode(stripslashes(un_escape_solr_special_chars(str_replace('"',"'",$text))), ENT_QUOTES, "UTF-8");
    }
endif;

if(!function_exists("feedspot_decrypt_ajax")):
    function feedspot_decrypt_ajax($q, $d = true) {
        $q = (string)filter_var($q, FILTER_SANITIZE_STRING);
        $newq = feedspot_decrypt($q);    
        $x = explode(",,", $newq);
        $args = [];
        foreach($x as $y):
            $xx = explode("=@", $y);
            $args[$xx[0]] = $xx[1];
        endforeach;
        return $args;
    }
endif;

if(!function_exists("replace_space_by_plus")):
    function replace_space_by_plus($string) {
        return str_replace(" ","+",$string);
    }
endif;

if(!function_exists("feedspot_decrypt")):
    function feedspot_decrypt($string) {
        $ENC_DEC_KEY = "─.鳶山夕景"; $result = '';
        $string = base64_decode(replace_space_by_plus($string));
        for($i = 0; $i < strlen($string); $i++):
            $char = substr($string, $i, 1);
            $keychar = substr($ENC_DEC_KEY, ($i % strlen($ENC_DEC_KEY))-1, 1);
            $char = chr(ord($char)-ord($keychar));
            $result .= $char;
        endfor;
        return $result;
    }
endif;

function generate_tk($id, $url, $template, $type = "") {
    return PUBLIC_URL."tk/".urlencode(feedspot_encrypt("id=@".$id.",,next=@".$url.",,type=@".$type.",,template=@".$template));
}

function feedspot_encrypt($string) {
    $ENC_DEC_KEY = "─.鳶山夕景";
    $result = '';
    for($i=0; $i<strlen($string); $i++) {
        $char = substr($string, $i, 1);
        $keychar = substr($ENC_DEC_KEY, ($i % strlen($ENC_DEC_KEY))-1, 1);
        $char = chr(ord($char)+ord($keychar));
        $result.=$char;
    }
    $result = base64_encode($result);
    return $result;
}

if(!function_exists("location_package")):
    function location_package($ids, $fill_empty = "") {
        $packages = $locations = [];
        foreach(explode(",", $ids) as $id):
            $id = trim($id);
        
            if(strpos($id, "l:") === 0):
                $location = explode("_", substr($id, 2));
                $locations[] = [
                    "country_id" => $location[0],
                    "state_id"   => $location[1] ?? $fill_empty,
                    "city_id"    => $location[2] ?? $fill_empty
                ];
            elseif(strpos($id, "p:") === 0):
                $packages[] = [
                    "key"  => substr($id, 2),
                    "type" => "package"
                ];
            endif;
        endforeach;
        
        if(empty($locations) && empty($packages)):
            return [];
        endif;

        return [
            "location" => $locations,
            "package"  => $packages
        ];
    }
endif;
if(!function_exists("split_location")):
    function split_location($ids, $fill_empty = "", $include_package = 0) {
        $id_arr = explode(",", $ids);
        $packages = $location = [];
        foreach($id_arr as $id):
            if(strpos($id, 'l:') !== false): 
                $id = str_replace("l:", "", $id);
                $arr = explode("_", $id);
                $country_id = $arr[0];
                $state_id = empty($arr[1]) ? $fill_empty : $arr[1];
                $city_id = empty($arr[2]) ? $fill_empty : $arr[2];                
                $location[] = ["country_id" => $country_id, "state_id" => $state_id, "city_id" => $city_id];
            endif;
            
            if($include_package && strpos($id, 'p:') !== false):
                $package = get_packages($id, 1);
                if(!empty($package)):
                    if($include_package == 1):
                        $data_arr = !empty($package["data"]) ? jsonDecode($package["data"]) : [];
                        foreach($data_arr as $arr):
                            $country_id = $arr["coi"];
                            $state_id = empty($arr["sti"]) ? $fill_empty : $arr["sti"];
                            $city_id = empty($arr["cid"]) ? $fill_empty : $arr["cid"];
                            $location[] = ["country_id" => $country_id, "state_id" => $state_id, "city_id" => $city_id];
                        endforeach;
                    elseif($include_package == 2):
                        $packages[] = [
                            "key"   => $package["id"],
                            "title" => $package["name"],
                            "type"  => "package"
                        ];
                    endif;
                endif;
            endif;
        endforeach;    
        
        $result = $include_package == 2 ? $packages : $location;        
        return $result;
    }
endif;

if(!function_exists("get_negated_terms")):
    function get_negated_terms($query) {
        /*preg_match_all('/-\w+/', $query, $matches);
        $negated =  array_map(fn($term) => ltrim($term, '-'), $matches[0]);
        $result = [
            "negated"   => $negated,
            "matched"   => $matches[0]
        ];
        return $result;*/
        preg_match_all('/(?<!\w)-\s*(\w+)/', $query, $matches);
        $negated = $matches[1];
        $result = [
            "negated" => $negated,
            "matched" => array_map(function($term) {
                return '-' . $term;
                }, $negated),
        ];
        return $result;
    }
endif;

if(!function_exists("negative_words_text")):
    function negative_words_text($text) {
        /*preg_match_all('/-"([^"]+)"|-([^\s]+)/', $text, $matches);
        $extracted = array_values(array_filter(array_merge($matches[1], $matches[2])));
        $remaining = trim(preg_replace('/-"([^"]+)"|-([^\s]+)/', '', $text));
        return [
            "extracted" => $extracted,
            "remaining" => $remaining
        ];*/
        preg_match_all('/(?<=\s|^)-"([^"]+)"|(?<=\s|^)-(\w+)/', $text, $matches);
        $extracted = array_values(array_filter(array_merge($matches[1], $matches[2])));
        $remaining = trim(preg_replace('/(?<=\s|^)-"([^"]+)"|(?<=\s|^)-(\w+)/', '', $text));
        return [
            "extracted" => $extracted,
            "remaining" => $remaining
        ];
    }
endif;

if(!function_exists("detect_query_type")):
    function detect_query_type($query) {
        $patterns = [
            "AND"   => '/\bAND\b/',
            "OR"    => '/\bOR\b/',
            "-"     => '/\s-\w+/',
            "QUOTE" => '/^".*"$/', //'/"\b.*?\b"/'
        ];
        $result = [];
        foreach ($patterns as $key => $pattern):
            if(preg_match($pattern, $query)):
                $result[] = $key;
            endif;
        endforeach;
        return !empty($result) ? $result : false;
    }
endif;

if(!function_exists("get_apple_id")):
    function get_apple_id($url) {
        $url = urldecode($url);
        
        if(stripos($url, '/channel/') !== false):
            return null;
        endif;
        
        if(preg_match('/\/id(\d+)/', $url, $matches)):
            $apple_id = $matches[1];
            $apple_id = $apple_id > 2147483647 ? null : $apple_id;
            return $apple_id;
        endif;         
        return null;
    }
endif;

if(!function_exists("is_apple_domain_site_url")):
    function is_apple_domain_site_url($input) {
        
        /*$pattern = "/(?:id)?(\d{8,12})/";
        if(preg_match($pattern, $input, $matches)):
            return ["apple_id" => $matches[1]]; 
        endif;*/
        
        //$pattern = '/(?<![A-Za-z])(?:id)?(\d{8,12})(?!\d)/i';
        $pattern = '/(?<![A-Za-z0-9])(id)?(\d{8,12})(?!\d)/i';
        if(preg_match($pattern, $input, $matches)):
            return ["apple_id" => $matches[2]];
        endif;
        
        $cleaned_url = preg_replace("/^(https?:\/\/)?(www\.)?/i", "", $input);
        $cleaned_url = rtrim($cleaned_url, "/");
        
        if(preg_match('/[a-z0-9.-]+\.[a-z]{2,6}\/.+/i', $cleaned_url)):
            return ["site_url" => $cleaned_url]; 
        elseif (preg_match('/^[a-z0-9.-]+\.[a-z]{2,6}$/i', $cleaned_url)):
            return ["feed_domain" => $cleaned_url]; 
        endif;
        
        return false; 
    }
endif;

function is_url($string) {
    return preg_match('/\b((https?:\/\/)?(www\.)?([a-z0-9-]+\.)+[a-z]{2,}(\/[^\s]*)?)\b/i', $string) === 1;
}

function is_hyphenated_query($query) {
    $query = trim($query);
    return preg_match('/^\b\w+\b-\b\w+\b$/', $query);
}

if(!function_exists("sort_left_to_right")):
    function sort_left_to_right($data, $search) {
        usort($data, function($a, $b) use ($search) {
            $search = strtolower($search);
            $aName = strtolower($a["title"]);
            $bName = strtolower($b["title"]);
            
            $posA = strpos($aName, $search);
            $posB = strpos($bName, $search);
            
            $posA = $posA === false ? PHP_INT_MAX : $posA;
            $posB = $posB === false ? PHP_INT_MAX : $posB;
            
            if($posA !== $posB):
                return $posA - $posB;
            endif;
            
            return strlen($aName) - strlen($bName);
        });
        
        return $data;
    }
endif;

if(!function_exists("is_mp_list_url")):
    function is_mp_list_url($query) {
        $query = trim($query);
        //$pattern = "#^https://(www\.)?millionpodcasts\.com/[a-z0-9-]+-podcasts/?$#i";
        //$pattern = "#^https://(www\.)?millionpodcasts\.com/[a-z0-9-]+-podcasts/?(\?.*)?$#i";
        //$pattern = "#^https://(?:www\.|dev\.)?millionpodcasts\.com/[a-z0-9-]+-podcasts/?(\?.*)?$#i";
        $pattern = "#^https://(?:www\.|dev\.)?millionpodcasts\.com/(?:[a-z0-9-]+-podcasts|podcasts-[a-z0-9-]+|[a-z0-9-]+-podcasts-[a-z0-9-]+)/?(?:\?.*)?$#i";
        return preg_match($pattern, $query) === 1;
    }
endif;

function is_podcast_list($url) {
    $url = trim($url);
    //$pattern = "#^https://(www\.)?podcast\.feedspot\.com/[a-z0-9_]+/?(\?.*)?$#i";
    $pattern = "#^https://(?:www\.)?podcast(?:qa|staging)?\.feedspot\.com/[a-z0-9_]+/?(\?.*)?$#i";
    return preg_match($pattern, $url) === 1;
}

if(!function_exists("stop_word_text")):
    function stop_word_text($query, $type) {
        $act_query = strtolower(trim($query));
        
        if(preg_match('/^best\b.*\bpodcasts?\b/', $act_query)):
            $act_query = preg_replace('/^best\b\s*/', '', $act_query);
            $act_query = !is_exact_match($type, $query, 0) ? preg_replace('/\bpodcasts?\b/', '', $act_query) : $act_query;
            return trim(preg_replace('/\s+/', ' ', $act_query));
        endif;        
        
        if(preg_match('/^top\b.*\bpodcasts?\b/', $act_query)):
            $act_query = preg_replace('/^top\b\s*/', '', $act_query);
            $act_query = !is_exact_match($type, $query, 0) ? preg_replace('/\bpodcasts?\b/', '', $act_query) : $act_query;
            return trim(preg_replace('/\s+/', ' ', $act_query));
        endif;        
        return $query;
    }
endif;

if(!function_exists("remove_btn_text_stop_word")):
    function remove_btn_text_stop_word($query, $type = "") {
        
        //if(preg_match('/^(top|best)\s+\d+/i', $query)):
        if(preg_match('/^(top|best)\s+\d+/i', $query) || preg_match('/^\d+\s+best/i', $query)):
            $query = preg_replace('/\b(top|best)\b/i', '', $query);
            $query = preg_replace('/\b\d+\b/', '', $query);
            $query = !is_exact_match($type, $query, 0) ? preg_replace('/\bpodcasts?\b/i', '', $query) : $query;
            $query = trim(preg_replace('/\s+/', ' ', $query));
        else:
            $query = stop_word_text($query, $type);
        endif;
        
        $query = !is_exact_match($type, $query, 0) ? preg_replace('/\bpodcasts?\b/i', '', $query) : $query;
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        return $query;
    }
endif;

if(!function_exists("is_valid_social_handle")):
    function is_valid_social_handle($input) {    
        //return preg_match('/^@[a-zA-Z0-9_]+$/', $input) === 1;
        //return preg_match('/^@[a-zA-Z0-9_-]+$/', $input) === 1;
        return preg_match('/^@[a-zA-Z0-9._-]+$/', $input) === 1;
    }
endif;


function get_base_domain($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if(!$host):return false; endif;
    
    $host = strtolower($host);
    $host = preg_replace('/^www\./', '', $host);
    
    $parts = explode(".", $host);
    $count = count($parts);
    
    if($count >= 2):
        return $parts[$count - 2] . '.' . $parts[$count - 1];
    endif;
    
    return $host;
}

if(!function_exists("extract_social_info")):
    function extract_social_info($input) {
        
        if(is_valid_social_handle($input)):
            return ["type" => "all", "handle" => $input];
        endif;
        
        $allowed = ['instagram.com', 'facebook.com', 'twitter.com', 'x.com', 'spotify.com', 'linkedin.com', 'youtube.com'];
        $domain = get_base_domain($input);
        
        if(!empty($domain) && !in_array($domain, $allowed)):
            return false; 
        endif;
        
        $patterns = [
            'instagram' => '#(?:https?:\/\/)?(?:www\.)?instagram\.com\/([^\/\?\#]+)#i', 
            'facebook'  => '#(?:https?:\/\/)?(?:www\.)?facebook\.com\/(?:(groups)\/([^\/\?\#]+)|profile\.php\?id=([^\/\?\#&]+)|([^\/\?\#]+))#i',
            'twitter'   => '#(?:https?:\/\/)?(?:www\.)?(?:twitter\.com|x\.com)\/([^\/\?\#]+)#i',
            'spotify'   => '#(?:https?:\/\/)?(?:open\.)?spotify\.com\/(user|artist|playlist|album|track|show|episode)\/([^\/\?\#]+)#i',
            'linkedin'  => '#(?:https?:\/\/)?(?:[a-z]+\.)?linkedin\.com\/(in|company|showcase|school)\/([^\/\?\#]+)#i',
            'youtube'   => '#(?:https?:\/\/)?(?:www\.)?youtube\.com\/(?:@|c\/|user\/|channel\/)?([^\/\?\#]+)#i',
        ];
        
        foreach($patterns as $type => $pattern):
            if(preg_match($pattern, $input, $matches)):
            
                if ($type == "facebook") {
                    if (!empty($matches[1]) && $matches[1] === "groups") {
                        return [
                            "type"   => "facebook",
                            "handle" => $matches[2]
                        ];
                    } elseif (!empty($matches[3])) {
                        return [
                            "type"   => "facebook",
                            "handle" => $matches[3]
                        ];
                    } else {
                        return [
                            "type"   => "facebook",
                            "handle" => $matches[4]
                        ];
                    }
                }
                
                if($type === "linkedin"):
                    return [
                        "type"      => $matches[1] === "company" ? "linkedin" : "linkedin",
                        "handle"    => $matches[2]
                    ];
                elseif($type === "spotify"):
                    return [
                        "type"   => "spotify",
                        "handle" => $matches[2]
                    ];
                endif;
                
                return [
                    "type"      => $type,
                    "handle"    => $matches[1]
                ];
            endif;
        endforeach;
        
        return false;
    }
endif;

if(!function_exists("sort_name_data")):
    function sort_name_data($docs, $query) {    
        $query = strtolower(trim($query));
        $exact_matches = [];
        
        foreach($docs as $doc):
            $feed_name = handleSpecialChar($doc["feed_name"], 1);
            $feed_name = strtolower(trim($feed_name));
            
            if($feed_name === $query):
                $exact_matches[] = $doc;
            endif;
        endforeach;
        
        if(!empty($exact_matches)):
            if(count($exact_matches) > 1):
                usort($exact_matches, function ($a, $b) {
                    return ($b['review_count'] ?? 0) <=> ($a['review_count'] ?? 0);
                });
            endif;
            return $exact_matches[0];
        endif;
        
        /*usort($docs, function ($a, $b) {
            return ($b['review_count'] ?? 0) <=> ($a['review_count'] ?? 0);
        });        
        return $docs[0];*/
        
        return [];
    }
endif;

if(!function_exists("resize_image_url")):
    function resize_image_url($url) {
        return preg_replace('#(feedspotfeed/)#', 'feedspotfeed/200/', $url);
    }
endif;

function country_by_code($code) {
    $code = strtoupper($code);
    $json = '{"UK":"United Kingdom", "BH-AR":"Bahrain", "CA-FR":"Canada", "EG-AR":"Egypt", "JO-AR":"Jordan", "KW-AR":"Kuwait", "QA-AR":"Qatar", "SA-AR":"Saudi Arabia", "AF":"Afghanistan","AL":"Albania", "AE-AR":"United Arab Emirates", "OM-AR":"Oman", "DZ":"Algeria","AS":"American Samoa","AD":"Andorra","AO":"Angola","AI":"Anguilla","AQ":"Antarctica","AG":"Antigua and Barbuda","AR":"Argentina","AM":"Armenia","AW":"Aruba","AP":"Asia\/Pacific Region","AU":"Australia","AT":"Austria","AZ":"Azerbaijan","BS":"Bahamas","BH":"Bahrain","BD":"Bangladesh","BB":"Barbados","BY":"Belarus","BE":"Belgium","BZ":"Belize","BJ":"Benin","BM":"Bermuda","BT":"Bhutan","BO":"Bolivia","BQ":"Bonaire, Sint Eustatius and Saba","BA":"Bosnia and Herzegovina","BW":"Botswana","BV":"Bouvet Island","BR":"Brazil","IO":"British Indian Ocean Territory","BN":"Brunei Darussalam","BG":"Bulgaria","BF":"Burkina Faso","BI":"Burundi","KH":"Cambodia","CM":"Cameroon","CA":"Canada","CV":"Cape Verde","KY":"Cayman Islands","CF":"Central African Republic","TD":"Chad","CL":"Chile","CN":"China","CX":"Christmas Island","CC":"Cocos (Keeling) Islands","CO":"Colombia","KM":"Comoros","CG":"Congo","CD":"Congo, The Democratic Republic of the","CK":"Cook Islands","CR":"Costa Rica","HR":"Croatia","CU":"Cuba","CY":"Cyprus","CZ":"Czech Republic","CI":"Cote d\'Ivoire","DK":"Denmark","DJ":"Djibouti","DM":"Dominica","DO":"Dominican Republic","EC":"Ecuador","EG":"Egypt","SV":"El Salvador","GQ":"Equatorial Guinea","ER":"Eritrea","EE":"Estonia","ET":"Ethiopia","FK":"Falkland Islands (Malvinas)","FO":"Faroe Islands","FJ":"Fiji","FI":"Finland","FR":"France","GF":"French Guiana","PF":"French Polynesia","TF":"French Southern Territories","GA":"Gabon","GM":"Gambia","GE":"Georgia","DE":"Germany","GH":"Ghana","GI":"Gibraltar","GR":"Greece","GL":"Greenland","GD":"Grenada","GP":"Guadeloupe","GU":"Guam","GT":"Guatemala","GG":"Guernsey","GN":"Guinea","GW":"Guinea-Bissau","GY":"Guyana","HT":"Haiti","HM":"Heard Island and Mcdonald Islands","VA":"Holy See (Vatican City State)","HN":"Honduras","HK":"Hong Kong","HU":"Hungary","IS":"Iceland","IN":"India","ID":"Indonesia","IR":"Iran, Islamic Republic Of","IQ":"Iraq","IE":"Ireland","IM":"Isle of Man","IL":"Israel","IT":"Italy","JM":"Jamaica","JP":"Japan","JE":"Jersey","JO":"Jordan","KZ":"Kazakhstan","KE":"Kenya","KI":"Kiribati","KR":"Korea, Republic of","KW":"Kuwait","KG":"Kyrgyzstan","LA":"Laos","LV":"Latvia","LB":"Lebanon","LS":"Lesotho","LR":"Liberia","LY":"Libyan Arab Jamahiriya","LI":"Liechtenstein","LT":"Lithuania","LU":"Luxembourg","MO":"Macao","MG":"Madagascar","MW":"Malawi","MY":"Malaysia","MV":"Maldives","ML":"Mali","MT":"Malta","MH":"Marshall Islands","MQ":"Martinique","MR":"Mauritania","MU":"Mauritius","YT":"Mayotte","MX":"Mexico","FM":"Micronesia, Federated States of","MD":"Moldova, Republic of","MC":"Monaco","MN":"Mongolia","ME":"Montenegro","MS":"Montserrat","MA":"Morocco","MZ":"Mozambique","MM":"Myanmar","NA":"Namibia","NR":"Nauru","NP":"Nepal","NL":"Netherlands","AN":"Netherlands Antilles","NC":"New Caledonia","NZ":"New Zealand","NI":"Nicaragua","NE":"Niger","NG":"Nigeria","NU":"Niue","NF":"Norfolk Island","KP":"North Korea","MK":"North Macedonia","MP":"Northern Mariana Islands","NO":"Norway","OM":"Oman","PK":"Pakistan","PW":"Palau","PS":"Palestinian Territory, Occupied","PA":"Panama","PG":"Papua New Guinea","PY":"Paraguay","PE":"Peru","PH":"Philippines","PN":"Pitcairn Islands","PL":"Poland","PT":"Portugal","PR":"Puerto Rico","QA":"Qatar","RE":"Reunion","RO":"Romania","RU":"Russian Federation","RW":"Rwanda","BL":"Saint Barthelemy","SH":"Saint Helena","KN":"Saint Kitts and Nevis","LC":"Saint Lucia","MF":"Saint Martin","PM":"Saint Pierre and Miquelon","VC":"Saint Vincent and the Grenadines","WS":"Samoa","SM":"San Marino","ST":"Sao Tome and Principe","SA":"Saudi Arabia","SN":"Senegal","RS":"Serbia","CS":"Serbia and Montenegro","SC":"Seychelles","SL":"Sierra Leone","SG":"Singapore","SX":"Sint Maarten","SK":"Slovakia","SI":"Slovenia","SB":"Solomon Islands","SO":"Somalia","ZA":"South Africa","GS":"South Georgia and the South Sandwich Islands","SS":"South Sudan","ES":"Spain","LK":"Sri Lanka","SD":"Sudan","SR":"Suriname","SJ":"Svalbard and Jan Mayen","SZ":"Swaziland","SE":"Sweden","CH":"Switzerland","SY":"Syrian Arab Republic","TW":"Taiwan","TJ":"Tajikistan","TZ":"Tanzania, United Republic of","TH":"Thailand","TL":"Timor-Leste","TG":"Togo","TK":"Tokelau","TO":"Tonga","TT":"Trinidad and Tobago","TN":"Tunisia","TR":"Turkey","TM":"Turkmenistan","TC":"Turks and Caicos Islands","TV":"Tuvalu","UG":"Uganda","UA":"Ukraine","AE":"United Arab Emirates","GB":"United Kingdom","US":"United States","UM":"United States Minor Outlying Islands","UY":"Uruguay","UZ":"Uzbekistan","VU":"Vanuatu","VE":"Venezuela","VN":"Vietnam","VG":"Virgin Islands, British","VI":"Virgin Islands, U.S.","WF":"Wallis and Futuna","EH":"Western Sahara","YE":"Yemen","ZM":"Zambia","ZW":"Zimbabwe","AX":"Aland Islands"}';
    $arr = json_decode($json, 1);    
    $country_name = isset($arr[$code]) ? $arr[$code] : $code;
    return $country_name;
}

if(!function_exists("extraxt_exact_match")):
    function extraxt_exact_match($query, $match, $debug = 0) {
        $results = [];
        preg_match_all('/(?:^|\s)(?:(AND|OR)\s+)?"([^"]+)"/i', $query, $matches, PREG_SET_ORDER);
        $match_type = $match == "any" ? "OR" : "AND";
        
        preg_match('/\b(AND|OR)\b/i', $query, $first_operator_match);
        $first_operator = isset($first_operator_match[1]) ? strtoupper($first_operator_match[1]) : null;
        
        foreach($matches as $i => $match):
            $operator = isset($match[1]) ? strtoupper(trim($match[1])) : null;
            if(empty($operator) && $i === 0 && !empty($first_operator)):
                $operator = $first_operator;
            endif;  
            
            if(empty($operator)):
                $operator = $match_type;
            endif;
            
            $results[] = [
                "query" => $match[2],
                "type"  => $operator
            ];
        endforeach;
        
        return $results;        
    }
endif;


function should_apply_comma_logic($query, $search_match = null) {    
    if ($search_match !== "exact" && (strpos($query, ',') !== false || strpos($query, " + ") !== false) && strpos($query, '"') === false) {
        return true;
    }
    return false;
}

function manage_comma_query($query, $search_match = null) {
    
    if(strpos($query, ',') === false || $search_match == "exact"):
        return $query;
    endif;   
    
    $parts = array_map('trim', str_getcsv($query));
    //$parts = array_map('trim', explode(',', $query));
    
    $parts = array_map(function ($term) {
    
        // split logical operators while keeping them
        //$tokens = preg_split('/\s+(AND|OR)\s+/i', $term, -1, PREG_SPLIT_DELIM_CAPTURE);
        $tokens = preg_split('/\s+(AND|OR)\s+/', $term, -1, PREG_SPLIT_DELIM_CAPTURE);
    
        foreach ($tokens as &$token) {
    
            $t = trim($token);
    
            // skip logical operators
            if (strcasecmp($t, 'AND') === 0 || strcasecmp($t, 'OR') === 0) {
                continue;
            }
    
            // already quoted
            
            if (preg_match('/^".+"$/', $t)) {
            //if(strpos($t, '"') !== false) {
                continue;
            }
    
            $words = preg_split('/\s+/', $t);
    
            $positive = [];
            $rest = [];
    
            foreach ($words as $w) {
                if (strpos($w, '-') === 0) {
                    $rest[] = $w;
                } else {
                    $positive[] = $w;
                }
            }
    
            if (count($positive) > 1) {
                $token = '"' . implode(' ', $positive) . '"';
                //$token = implode(' ', $positive);
            } else {
                $token = implode(' ', $positive);
            }
    
            if (!empty($rest)) {
                $token .= ' ' . implode(' ', $rest);
            }
        }
    
        return implode(' ', $tokens);
    
    }, $parts);
    
    $query = implode(' OR ', $parts);    
    return $query;
}

function has_advanced_query($query) {
    return preg_match('/\b(AND|OR)\b|".+?"/i', $query) === 1;
}

function parse_query($input) {
    $result = [
        "AND"     => [],
        "OR"      => [],
        "DEFAULT" => []
    ];

    $input = trim($input);
    $input = preg_replace('/\s+/', ' ', $input);

    preg_match_all('/"[^"]+"|\S+/', $input, $matches);
    $tokens = $matches[0];

    $hasAnd = in_array("AND", $tokens);
    $hasOr  = in_array("OR", $tokens);

    if (!$hasAnd && !$hasOr) {
        $result["DEFAULT"][] = $input;
        return $result;
    }

    /* split OR groups */
    $groups = [];
    $current = [];

    foreach ($tokens as $token) {

        if ($token === "OR") {
            $groups[] = $current;
            $current = [];
            continue;
        }

        $current[] = $token;
    }

    if ($current) {
        $groups[] = $current;
    }

    foreach ($groups as $group) {

        $andTerms = [];
        $term = "";

        foreach ($group as $token) {

            if ($token === "AND") {

                if ($term !== "") {
                    $andTerms[] = trim($term);
                    $term = "";
                }

                continue;
            }

            if ($term === "") {
                $term = $token;
            } else {
                $term .= " " . $token;
            }
        }

        if ($term !== "") {
            $andTerms[] = trim($term);
        }

        /* explicit AND */
        if (count($andTerms) > 1) {

            foreach ($andTerms as $t) {
                $result["AND"][] = $t;
            }

        } else {

            $term = $andTerms[0];

            /* implicit AND only when no explicit AND exists in query */
            if (!$hasAnd && strpos($term, ' ') !== false && $term[0] !== '"') {

                $parts = preg_split('/\s+/', $term);

                foreach ($parts as $p) {
                    $result["AND"][] = $p;
                }

            } else {

                $result["OR"][] = $term;

            }

        }
    }

    return $result;
}

function extract_exact_terms($queryParts, $match) {
    $queryParts["EXACT"] = [];
    foreach(["AND", "OR", "DEFAULT"] as $type):    
        if(empty($queryParts[$type])):
            continue;
        endif;        
        foreach($queryParts[$type] as $key => $term):
            if(preg_match('/^"(.*)"$/', $term, $match)):
                
                $match_type = $type;
                if($type == "DEFAULT"):
                    $match_type = $match == "any" ? "OR" : "AND";
                endif;                
            
                $queryParts["EXACT"][] = ["query" => $match[1], "type" => $match_type];
                unset($queryParts[$type][$key]);
            endif;
        endforeach;
        $queryParts[$type] = array_values($queryParts[$type]);
    endforeach;
    return $queryParts;
}

if(!function_exists("get_static_data")):
    function get_static_data($name) {
        $file_name = DOCUMENT_ROOT."Static/$name.json";
        $arr = [];
        if(file_exists($file_name)):
            $json = file_get_contents($file_name);
            $arr = jsonDecode($json);
        endif;    
        return $arr;
    }
endif;

if(!function_exists("detect_device_type")):
    function detect_device_type($ua = "") {
        $ua = empty($ua) ? strtolower($_SERVER['HTTP_USER_AGENT']) : strtolower($ua);
        
        if(preg_match('/ipad|tablet|playbook|silk|kindle/i', $ua)):
            return "tablet";
        endif;
        
        if(preg_match('/mobi|iphone|ipod|android(?!.*tablet)|blackberry|bb10|phone/i', $ua)):
            return "mobile";
        endif;
        
        return "desktop";
    }
endif;

if(!function_exists("dot_to_long_ip")):
    function dot_to_long_ip($ip) {
        
        if(empty($ip)):
            return false;
        endif;
        
        if(preg_match("/\:/", $ip)):
            $int = inet_pton($ip);
            $bits = 15;
            $ipv6long = 0;
            while($bits >= 0):
                $bin = sprintf("%08b", (ord($int[$bits])));
                $ipv6long = $ipv6long ? $bin . $ipv6long : $bin;
                $bits--;
            endwhile;
            return gmp_strval(gmp_init($ipv6long, 2), 10);
        endif;
        
        $ips = explode(".", $ip);
        return ($ips[3] + $ips[2] * 256 + $ips[1] * 256 * 256 + $ips[0] * 256 * 256 * 256);
    }
endif;

if(!function_exists("get_email_domain")):
    function get_email_domain($email) {
        $email = trim($email);        
        if(preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._%+-]{0,62}[A-Za-z0-9])?@(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/i', $email)):
            return substr(strrchr($email, "@"), 1);
        endif;
        
        return false;
    }
endif;

if(!function_exists("google_captcha_score")):
    function google_captcha_score($token) {    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GOOGLE_CAPTCHA_VERIFY_URL_V3);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(["secret" => GOOGLE_CAPTCHA_SECRET_KEY_V3, "response" => $token]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        
        $v3_score = 0;
        if($result["success"] == 1):
            $v3_score = $result["score"];
        endif;
        
        return $v3_score;
    }
endif;

if(!function_exists("is_valid_v2_captcha")):
    function is_valid_v2_captcha($token) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, GOOGLE_CAPTCHA_VERIFY_URL_V2);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, ["secret" => GOOGLE_CAPTCHA_SECRET_KEY_V2, "response" => $token]);
        $resp = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        if($resp["error-codes"]):
            return false;
        endif;
        
        return true;
    }
endif;

if(!function_exists("is_exact_match")):
    function is_exact_match($type, $query, $special = 1) {    
        if($type == "exact" || preg_match('/^".*"$/', $query)):
            return true;
        endif;    
        
        if($special && preg_match('/[^\x00-\x7F]/', $query)):
            return true;
        endif;
        
        return false;
    }
endif;

if(!function_exists("get_google_profile")):
    function get_google_profile($token) {    
        $ch = curl_init(GOOGLE_PROFILE_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if($code !== 200 || empty($response)):
            return false;
        endif;
        
        $profile = json_decode($response, true);
        return $profile;
    }
endif;

if(!function_exists("format_number")):
    function format_number($number) {
        if($number >= 1000000000):
            return round($number / 1000000000, 1)."B";
        endif;
        
        if($number >= 1000000):
            return round($number / 1000000, 1)."M";
        endif;
        
        if($number >= 1000):
            return round($number / 1000, 1)."K";
        endif;
        
        return $number;
    }
endif;

if(!function_exists("timer_mark")):
    function timer_mark($label) {
        static $start = null;
        static $steps = [];
        
        $start = defined("APP_START") ? APP_START : microtime(true);
        /*if($start === null):
            $start = microtime(true);   
        endif;*/
        
        $time = round(microtime(true) - $start, 4);
        $steps[] = [
            "label" => $label,
            "time"  => $time
        ];    
        return $steps;
    }
endif;

if(!function_exists("timer_get")):
    function timer_get() {
        return timer_mark("end"); 
    }
endif;

if(!function_exists("is_any_c1_applied")):
    function is_any_c1_applied($payload = []) {
        $keys = ["community", "apple_review", "language", "apple_rating", "include_keyword", "exclude_keyword", "listener_age", "listener_income", "listener_gender", "apple_review", "min_rating", "max_rating", "search_match", "ueng_range_value", "search_in", "gender", "location", "podcast_network", "beats", "episode_length", "user_engagement", "exclude_permalink", "other_attributes", "start_date", "audience_type", "us_region"];
        foreach($keys as $key):        
            $value = trim($payload[$key]);
        
            if($key == "location" && !empty($payload["auto_location"])):
                continue;
            endif;
            
            if($key == "search_in" && !empty($payload["auto_search_in"])):
                continue;
            endif;
            
            if($key == "search_match"):
                if(!empty($value) && $value != "all"):
                    return true;
                endif;
            elseif(!empty($value)):
                return true;
            endif;
        endforeach;        
        return false;
    }
endif;

if(!function_exists("only_special_char")):
    function only_special_char($input) {
        $input = trim($input);
        
        if(empty($input)):
            return '';
        endif;
        
        /*if(!preg_match('/[a-zA-Z0-9]/', $input)):
            return '';
         endif;*/
        
        if(!preg_match('/[\p{L}\p{N}]/u', $input)):
            return '';
        endif;        
        return $input;
    }
endif;



if(!function_exists("check_spam_user")):
    function check_spam_user($name, $email) {
        
        $name = json_encode($name);
        $safe_name = trim($name, '"');
        
        $prompt = "You are an AI spam detection classifier. Evaluate whether a user is legitimate or spam based on name and email.
        
Input:
Name: {$safe_name}
Email: {$email}

Analyze:
- Does the name look like a real human name?
- Does the email match the name?
- Does it contain random or machine-generated patterns?
- Known disposable email domains?
- Too many numbers or special characters?
- Bot-like naming behavior?

Output ONLY JSON in this exact structure:
{
  \"spam_score\": 0-100,
  \"verdict\": \"real_user\" | \"likely_spam\",
  \"confidence\": \"high\" | \"medium\" | \"low\",
  \"signals\": [
    \"reason 1\",
    \"reason 2\",
    \"reason 3\"
  ]
}";
        
        $data = [
            "model" => "gpt-4o-mini",
            "messages" => [
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.1,
            "max_tokens" => 100
        ];
        
        $ch = curl_init("https://api.openai.com/v1/chat/completions");        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer ".OPENAI_API_KEY
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $json = json_decode($response, true);
        $content = $json["choices"][0]["message"]["content"] ?? "";
        
        return json_decode($content, true); // final JSON from model
    }
endif;

if(!function_exists("is_apple_relay_email")):
    function is_apple_relay_email($email) {
        
        if(preg_match('/^([^@]+)@privaterelay\.appleid\.com$/i', $email, $m)):
            return $m[1]; 
        endif;
        
        return null;
    }
endif;

if(!function_exists("escape_query")):
    function escape_query($query, $type = "search") {
        //$query = addslashes(addslashes($query));        
        /*if($type == "search"):
            $query = addslashes(addslashes($query));
        else:
            $query = addslashes($query); 
        endif;*/
        $query = addslashes($query); 
        
        if(preg_match('/[^a-zA-Z0-9 ]/', $query)):
           $query = preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $query);
           $query = escape_solr_reserved_chars($query);
           
           $query = str_replace("&", "__AAMPP__", $query); //new           
           $query = htmlentities($query, ENT_NOQUOTES, "UTF-8");	
           $query = str_replace("__AAMPP__", "&", $query); //new
        endif;	   
        
        return $query;
    }	
endif;

if(!function_exists("build_query")):
    function build_query($input, $payload = []) {
        $column_field = $or_parts = $arr = $ad_query = $query = [];
        
        if(!empty($input["OR"])):
           foreach ($input["OR"] as $field => $values):
    	       $or_parts[] = "$field:(".implode(" OR ", $values).")";
    	    endforeach;
        endif;
        
        if(!empty($or_parts)):
            $query[] = implode(" OR ", $or_parts);
        endif;
        
        if(!empty($input["AND"])):
    	    foreach ($input["AND"] as $field => $values):
        	    $column_name = str_replace(["_text_general", "_stem", "_ngram", "_exact"], "", $field);
        	    $column_field[$column_name][] = "$field:(" . implode(" AND ", $values) . ")";
    	    endforeach;
        endif;
        
        foreach($column_field as $fields):
            //$ad_query[] = "(".implode(" AND ", $fields).")";
            $ad_query[] = implode(" AND ", $fields);
        endforeach;
        
        if(!empty($query)):
            $arr[] = "(".implode(" OR ", $query).")";
        endif;
        
        if(!empty($ad_query)):
            $arr[] = "(".implode(" OR ", $ad_query).")";
        endif;
        
        //$select = auto_implode($arr, $payload);
        //$select = "(".implode(" OR ", $arr).")";
        $select = implode(" OR ", $arr);
        
        if($payload["debug"] > 0):
            //echo "$select <pre>";
            //print_r($arr);
            //die;
        endif;       
        
        return $select;
    }
endif;

if(!function_exists("auto_implode")):
    function auto_implode($parts, $payload = []) {
        preg_match_all('/\b(AND|OR|-)\b/i', $payload["actual_query"] ?? "", $matches);
        $operators = array_map("strtoupper", $matches[1]);
        
        if($payload["debug"] > 0):
            //echo "<pre>";
            //print_r($operators);
            //die;
        endif;
        
        $join_operator = "AND";
        if(in_array("AND", $operators) && in_array("OR", $operators)):
            $firstAnd = array_search("AND", $operators);
            $firstOr = array_search("OR", $operators);
            $join_operator = ($firstAnd < $firstOr) ? "OR" : "AND";
        elseif(in_array("AND", $operators)):
            $join_operator = "AND";
        elseif(in_array("OR", $operators)):
            $join_operator = "OR";
        endif;
        
        if($payload["debug"] > 0):
            //echo " $join_operator <pre>";
            //print_r($operators);
            //die;
        endif;
        
        return "(".implode(" $join_operator ", $parts).")";
        //return implode(" $join_operator ", $parts);
    }
endif;

if(!function_exists("get_ip_details")):
    function get_ip_details($ip = null) {
		$ip = empty($ip) ? get_client_ip() : $ip;
		$response = null;
		
		try {		    
			$reader = new \GeoIp2\Database\Reader(DOCUMENT_ROOT."Library/maxmind/GeoLite2-City/GeoLite2-City.mmdb");
			$record = $reader->city($ip);
			$country_code = $record?->country?->isoCode ?? null;
			$timezone = $record?->location?->timeZone ?? "UTC";
			$city_name = $record?->city?->name ?? null; 
			
			$response = [
			    "country_code"       => $country_code,
			    "timezone"           => $timezone,
			    "city"               => $city_name
			];
			
		} catch (Exception $e) {		    
			
		}
		return $response;
	}
endif;

if(!function_exists("query_location")):
    function query_location($text) {
        $patterns = [
            "l:229" => ["in the us", "in the u.s.", "in the united states"],
            "l:14"  => ["in the au", "in the australia", "in au", "in australia"],
            "l:228" => ["in the uk", "in the united kingdom", "in uk", "in united kingdom"],
            "l:41"  => ["in the ca", "in the canada", "in ca", "in canada"]
        ];    
        $locations = [];    
        foreach($patterns as $country => $phrases):
            foreach($phrases as $phrase):
                $regex = '/(?<!\w)' . preg_quote($phrase, '/') . '(?!\w)/i';
                if(preg_match_all($regex, $text, $matches)):
                    foreach ($matches[0] as $m):
                        $locations[] = $country;
                    endforeach;
                    $text = preg_replace($regex, '', $text);    
                endif;    
            endforeach;
        endforeach;
        $cleanQuery = trim(preg_replace('/\s+/', ' ', $text));
        return [
            "location" => array_values(array_unique($locations)),
            "query" => $cleanQuery
        ];
    }
endif;

function remove_in_year($text) {
    return trim(preg_replace('/\bin\s+\d{4}\b/i', '', $text));
}

function shortcodes() {
    $short_codes[] = [
        "name"  => "First Name",
        "code"  => "{{first_name}}",
        "key"   => "first_name",
        "desc"  => "Author first name"
    ];        
    $short_codes[] = [
        "name"  => "Last Name",
        "code"  => "{{last_name}}",
        "key"   => "last_name",
        "desc"  => "Author last name"
    ];        
    $short_codes[] = [
        "name"  => "Site Name",
        "code"  => "{{site_name}}",
        "key"   => "site_name",
        "desc"  => "Website Name"
    ]; 
    return $short_codes;
}

function getNameFromEmail($email) {
    $local = explode('@', $email)[0];
    $name = str_replace(['.', '_', '-', '+'], ' ', $local);
    $name = preg_replace('/\d+/', '', $name);
    $name = ucwords(trim($name));
    return $name ?: $email;
}

function solr_escape($string) {
    $pattern = '/([+\-!(){}\[\]^"~*?:\\/]|&&|\|\|)/';
    $string  = preg_replace($pattern, '\\\\$1', $string);

    if(substr($string, -1) === '\\'):
        $string .= '\\';
    endif;

    return $string;
}
    
function char_image($str) {
    $firstAlpha = null;
    foreach(str_split($str) as $ch):
        if(ctype_alpha($ch)):
            $firstAlpha = strtoupper($ch);
            break;
        endif;
    endforeach;
    
    if($firstAlpha === null):
        return AVATAR_BASE_URL."A.jpg";
    endif;
    
    return AVATAR_BASE_URL . $firstAlpha . ".jpg";        
}
    

function replace_apple_podcast_country($url, $country = 'us') {
    if(empty($url) || !is_string($url)) {
        return $url;
    }
    
    $country = (string) $country;
    if(strpos($country, '-') !== false) {
        $country = explode('-', $country, 2)[0];
    }
    
    $country = strtolower(trim($country));
    if(!preg_match('/^[a-z]{2}$/i', $country)) {
        return $url;
    }
    
    if(preg_match('#https://podcasts\.apple\.com/[a-z]{2}/(?:podcast|id)/#i', $url)) {
        return preg_replace('#(https://podcasts\.apple\.com/)[a-z]{2}(/)#i', '${1}' . $country . '${2}', $url, 1);
    }
    
    if(preg_match('#https://podcasts\.apple\.com/(?:podcast|id)/#i', $url)) {
        return preg_replace('#(https://podcasts\.apple\.com)(/(?:podcast|id)/)#i', '${1}/' . $country . '${2}', $url, 1);
    }    
    return $url;
}

if(!function_exists("get_restatement_terms")):
    function get_restatement_terms($topic) {
        static $cache = [];
        $topic = trim(strtolower($topic));
        if(isset($cache[$topic])):
            return $cache[$topic];
        endif;
        
        $result = [];
        $arr = get_static_data("topic_expansions"); // confirm actual filename with team
        if(!empty($arr)):
            foreach($arr as $row):
                if(!isset($row[0], $row[1], $row[4])):
                    continue;
                endif;
                if(trim(strtolower($row[0])) == $topic && trim(strtolower($row[4])) == "restatement"):
                    $result[trim(strtolower($row[1]))] = true;
                endif;
            endforeach;
        endif;
        
        $cache[$topic] = $result;
        return $result;
    }
endif;


if(!function_exists("get_topic_term_types")) {
    function get_topic_term_types($topic) {
        static $cache = [];
        $topic = trim(strtolower($topic));
        if(isset($cache[$topic])) {
            return $cache[$topic];
        }
        
        $result = ["alias" => [], "restatement" => [], "broader" => [], "excluded" => []];
        $arr = get_static_data("topic_expansions");
        if(!empty($arr)) {
            foreach($arr as $row) {
                if(!isset($row[0], $row[1], $row[3], $row[4])) {
                    continue;
                }
                if(trim(strtolower($row[0])) != $topic) {
                    continue;
                }
                //$term_key = trim(strtolower($row[1]));
                $term_key = trim(strtolower(str_replace("-", " ", $row[1])));
                if(trim(strtolower($row[3])) == "yes") {
                    $result["alias"][$term_key] = true;
                }
                $type = trim(strtolower($row[4]));
                if($type == "restatement") {
                    $result["restatement"][$term_key] = true;
                } elseif($type == "broader") {
                    $result["broader"][$term_key] = true;
                } elseif($type == "narrower" || $type == "adjacent") {
                    $result["excluded"][$term_key] = true;
                }
            }
        }
        
        $cache[$topic] = $result;
        return $result;
    }
}

?>
