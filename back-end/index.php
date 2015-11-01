<?php
$street = $city = "";
$state = "hehe";
$degree = "us";

function weather_image($head)
{
    if (strcmp($head, 'clear-day') == 0) return "clear.png";
    if (strcmp($head, 'clear-night') == 0) return "clear_night.png";
    if (strcmp($head, 'rain') == 0) return "rain.png";
    if (strcmp($head, 'snow') == 0) return "snow.png";
    if (strcmp($head, 'sleet') == 0) return "sleet.png";
    if (strcmp($head, 'wind') == 0) return "clear.png";
    if (strcmp($head, 'fog') == 0) return "wind.png";
    if (strcmp($head, 'cloudy') == 0) return "cloudy.png";
    if (strcmp($head, 'partly-cloudy-day') == 0) return "cloud_day.png";
    if (strcmp($head, 'partly-cloudy-night') == 0) return "cloud_night.png";
}
function precipitation($val)
{
    if ($val >= 0 && $val<0.002) return "None";
    if ($val >= 0.002 && $val<0.017) return "Very Light";
    if ($val >= 0.0017 && $val<0.01) return "Light";
    if ($val >= 0.01 && $val<0.04) return "Moderate";
    if ($val >= 0.04) return "Heavy";
    return "None";
}
function temperature($val)
{
    if (strcmp($val, 'us') == 0) return '&deg;F';
    return '&deg;C';
}
function windSpeed($val){
    if (strcmp($val, 'us') == 0) return 'mph';
    return 'm/s';
}
function visibility($val){
    if (strcmp($val, 'us') == 0) return 'mi';
    return 'km';
}
function pressure($val){
    if (strcmp($val, 'us') == 0) return 'mb';
    return 'hPa';
}

?>
<?php
header('Access-Control-Allow-Origin: *');

$googleUrl = "http://maps.google.com/maps/api/geocode/xml?address=";
if (!empty($_GET["street"])) {
    $street = $_GET["street"];
    $googleUrl .= $_GET["street"];
    $googleUrl .= ",";
}
if (!empty($_GET["city"])) {
    $city = $_GET["city"];
    $googleUrl .= $_GET["city"];
    $googleUrl .= ",";
}
if (!empty($_GET["state"])) {
    $state = $_GET["state"];
    $googleUrl .= $_GET["state"];
}
if (!empty($_GET["degree"])) {
    $degree = $_GET["degree"];
}
$temperature_unit=temperature($degree);
$dewPoint_unit=temperature($degree);
$windSpeed_unit=windSpeed($degree);
$pressure_unit=pressure($degree);
$visibility_unit=visibility($degree);

$xml = simplexml_load_file($googleUrl) or die("Error: Cannot create object");
$status = $xml->status;
$weather_data=new stdClass();
$weather_data->data=new stdClass();
$weather_data->data->units=new stdClass();
$weather_data->data->currently=new stdClass();
$weather_data->data->hourly=new stdClass();
$weather_data->data->daily=new stdClass();
if (strcmp($status, "OK") == 0) {

    $weather_data->hasError=false;
    $lat = $xml->result->geometry->location->lat;
    $lng = $xml->result->geometry->location->lng;

    $weather_data->data->lat=$lat;
    $weather_data->data->lng=$lng;
    $weather_data->data->units->temperature_unit=$temperature_unit;
    $weather_data->data->units->dewPoint_unit=$dewPoint_unit;
    $weather_data->data->units->windSpeed_unit=$windSpeed_unit;
    $weather_data->data->units->pressure_unit=$pressure_unit;
    $weather_data->data->units->visibility_unit=$visibility_unit;

    $jsonUrl = 'https://api.forecast.io/forecast/f4abdbcbc60c0ad7c8e204995b96b736/';
    $jsonUrl .= $lat . ',' . $lng . '?' . 'units=' . $degree . '&exclude=flags';
    $jsonFile = file_get_contents($jsonUrl);
    $doc = json_decode($jsonFile);

    $time_zone = $doc->timezone;
    $weather_data->data->time_zone=$time_zone;
    date_default_timezone_set($time_zone);

    //For current
    $sunrise = date("h:i A", $doc->daily->data[0]->sunriseTime);
    $sunset = date("h:i A", $doc->daily->data[0]->sunsetTime);
    //$weather_data->data->currently=$doc->currently;
    $weather_data->data->currently->summary=$doc->currently->summary;
    $weather_data->data->currently->sunriseTime=$sunrise;
    $weather_data->data->currently->sunsetTime=$sunset;
    $weather_data->data->currently->temperature=(int)$doc->currently->temperature;
    $weather_data->data->currently->temperatureMax=(int)($doc->daily->data[0]->temperatureMax).'&deg;';
    $weather_data->data->currently->temperatureMin=(int)($doc->daily->data[0]->temperatureMin).'&deg;';
    $weather_data->data->currently->icon=weather_image($doc->currently->icon);
    $weather_data->data->currently->precipProbability=$weather_data->data->currently->precipProbability*100 .'%';
    $weather_data->data->currently->precipIntensity=precipitation($doc->currently->precipIntensity);
    $weather_data->data->currently->windSpeed=number_format($doc->currently->windSpeed,2,'.','') .$windSpeed_unit;
    $weather_data->data->currently->dewPoint=number_format($doc->currently->dewPoint,2,'.','') .$dewPoint_unit;
    $weather_data->data->currently->humidity=(int)($doc->currently->humidity*100) .'%';
    $weather_data->data->currently->visibility=number_format($doc->currently->visibility,2,'.','').$visibility_unit;


    //$weather_data->data->hourly=$doc->hourly;
    $weather_data->data->hourly->summary=$doc->hourly->summary;
    $weather_data->data->hourly->data=[];
    foreach ($doc->hourly->data as $row){
        $hourly=new stdClass();
        $hourly->time=date("h:i:A",$row->time);
        $hourly->summary=weather_image($row->icon);
        $hourly->cloudCover=(int)($row->cloudCover*100) .'%';
        $hourly->temp=number_format($row->temperature,'2','.','');
        $hourly->wind=$row->windSpeed .$windSpeed_unit;
        $hourly->humidity=(int)($row->humidity*100) .'%';
        $hourly->visibility=$row->visibility .$visibility_unit;
        $hourly->pressure=$row->pressure .$pressure_unit;
        array_push($weather_data->data->hourly->data,$hourly);
    }

    //$weather_data->data->daily=$doc->daily;
    $weather_data->data->daily->data=[];
    foreach($doc->daily->data as $row){
        $daily=new stdClass();
        $daily->summary=$row->summary;
        $daily->day=date("l",$row->time);
        $daily->mDate=date("M d",$row->time);
        $daily->icon=weather_image($row->icon);
        $daily->temperatureMax=(int)($row->temperatureMax) .'&deg;';
        $daily->temperatureMin=(int)($row->temperatureMin) .'&deg;';
        $daily->wind=$row->windSpeed .$windSpeed_unit;
        $daily->humidity=(int)($row->humidity*100) .'%';
        $daily->visibility=$row->visibility .$visibility_unit;
        $daily->pressure=$row->pressure .$pressure_unit;
        $daily->sunriseTime=date("h:i A", $row->sunriseTime);
        $daily->sunsetTime=date("h:i A", $row->sunsetTime);
        array_push($weather_data->data->daily->data,$daily);
    }
    echo json_encode($weather_data);
}
?>

