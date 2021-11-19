<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2004 - 2021 Packet Tide, LLC.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
PACKET TIDE, LLC BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of Packet Tide, LLC shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from Packet Tide, LLC.
*/


/**
 * World_weather Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author          Paket Tide
 * @copyright       Copyright (C) 2004 - 2021 Packet Tide, LLC.
 * @link			https://github.com/EllisLab/World-Weather
 */
class World_weather
{
    public $icao           = '';// station id
    public $weather_data  = '';
	public $return_data    = '';

    public $cache_dir      = 'world_weather_cache';
    public $cache_path     = '';
    public $cache_tpath    = '';
    public $cache_refresh  = 180;// minutes
    public $cache_data     = '';
    public $cache_time     = '';

	/**
	 * Constructor
	 *
	 */
    function __construct()
    {
        ee()->lang->loadfile('world_weather');

        $this->cache_dir = APPPATH.'cache/'.$this->cache_dir.'/';
    }

	// --------------------------------------------------------------------

	/**
	* Current weather
	*
	* Function description
	*
	* @access   public
	* @return   type
	*/
    function current()
    {
        /*---------------------------------------
         Validate ICAO
        -----------------------------------------*/

        if ( ! $this->valid_icao(ee()->TMPL->fetch_param('station')))
        {
            return;
        }

        /*---------------------------------------
         Fetch Tag Parameters
        -----------------------------------------*/

        $this->icao          = ee()->TMPL->fetch_param('station');
        $this->cache_refresh = ( ! is_numeric(ee()->TMPL->fetch_param('cache_refresh'))) ? $this->cache_refresh
                                                                                    : ee()->TMPL->fetch_param('cache_refresh');

        /*---------------------------------------
         Check Cache and Fetch Cache
        -----------------------------------------*/
        //
        $this->cache_path  = $this->cache_dir.md5('current'.$this->icao);
        $this->cache_tpath = $this->cache_path.'_t';

        $this->fetch_cache();

        if (trim($this->cache_data) == '')
        {
            $host = 'tgftp.nws.noaa.gov';
            $path = '/data/observations/metar/stations/'.$this->icao.'.TXT';

            if ($this->retrieve_data_socket($host, $path) == false)
            {
                return;
            }
        }

        if (stristr($this->cache_data, 'Not Found'))
        {
        	return;
        }

        /*---------------------------------------
         Parse the metar
        -----------------------------------------*/

        $this->parse_metar_data();

        if (count($this->weather_data) == 0)
        {
            return;
        }

        /*---------------------------------------
         Prep single tag data
        -----------------------------------------*/

        $tags = $this->weather_data;

        $NA =  lang('not_applicable');

        $tags['wind_direction'] = ( ! isset($tags['wind_direction'])) ? $NA : $tags['wind_direction'];
        $tags['wind_degrees']   = ( ! isset($tags['wind_degrees']))   ? $NA : $tags['wind_degrees'];
        $tags['wind_speed_mph'] = ( ! isset($tags['wind_speed_mph'])) ? $NA : $tags['wind_speed_mph'];
        $tags['wind_speed_kmh'] = ( ! isset($tags['wind_speed_kmh'])) ? $NA : $tags['wind_speed_kmh'];
        $tags['wind_speed_kt']  = ( ! isset($tags['wind_speed_kt']))  ? $NA : $tags['wind_speed_kt'];
        $tags['wind_gust_mph']  = ( ! isset($tags['wind_gust_mph']))  ? $NA : $tags['wind_gust_mph'];
        $tags['wind_gust_kmh']  = ( ! isset($tags['wind_gust_kmh']))  ? $NA : $tags['wind_gust_kmh'];
        $tags['wind_gust_kt']   = ( ! isset($tags['wind_gust_kt']))   ? $NA : $tags['wind_gust_kt'];
        $tags['temperature_f']  = ( ! isset($tags['temperature_f']))  ? $NA : $tags['temperature_f'];
        $tags['temperature_c']  = ( ! isset($tags['temperature_c']))  ? $NA : $tags['temperature_c'];
        $tags['feels_like_f']   = ( ! isset($tags['feels_like_f']))   ? $NA : $tags['feels_like_f'];
        $tags['feels_like_c']   = ( ! isset($tags['feels_like_c']))   ? $NA : $tags['feels_like_c'];
        $tags['dew_point_f']    = ( ! isset($tags['dew_point_f']))    ? $NA : $tags['dew_point_f'];
        $tags['dew_point_c']    = ( ! isset($tags['dew_point_c']))    ? $NA : $tags['dew_point_c'];
        $tags['humidity']       = ( ! isset($tags['humidity']))       ? $NA : $tags['humidity'];
        $tags['heat_index_f']   = ( ! isset($tags['heat_index_f']))   ? $NA : $tags['heat_index_f'];
        $tags['heat_index_c']   = ( ! isset($tags['heat_index_c']))   ? $NA : $tags['heat_index_c'];
        $tags['barometer_in']   = ( ! isset($tags['barometer_in']))   ? $NA : $tags['barometer_in'];
        $tags['barometer_hpa']  = ( ! isset($tags['barometer_hpa']))  ? $NA : $tags['barometer_hpa'];
        $tags['sky_condition']  = ( ! isset($tags['sky_condition']))  ? $NA : $tags['sky_condition'];
        $tags['visibility_mi']  = ( ! isset($tags['visibility_mi']))  ? $NA : $tags['visibility_mi'];
        $tags['visibility_km']  = ( ! isset($tags['visibility_km']))  ? $NA : $tags['visibility_km'];
        $tags['condition']      = ( ! isset($tags['condition']))      ? lang('clear') : $tags['condition'];
        $tags['last_update']    = ( ! isset($tags['last_update']))   ? $this->get_filemtime() : $tags['last_update'];


        /*----------------------------------------
         Parse the Template
        ----------------------------------------*/

        $tagdata = ee()->TMPL->tagdata;

        /*----------------------------------------
         Parse scripted conditionals
        ----------------------------------------*/

        foreach (ee()->TMPL->var_cond as $val)
        {
            $cond = $this->functions->prep_conditional($val['0']);

            $lcond = substr($cond, 0, strpos($cond, ' '));
            $rcond = substr($cond, strpos($cond, ' '));

            if (isset($tags[ $val['3'] ]))
            {
                $lcond = str_replace($val['3'], "\$tags['".$val['3']."']", $lcond);
                $cond = $lcond.' '.$rcond;

                $cond = str_replace("\|", "|", $cond);

                eval("\$result = ".$cond.";");

                if ($result)
                {
                    $tagdata = str_replace($val['1'], $val['2'], $tagdata);
                }
                else
                {
                    $tagdata = str_replace($val['1'], '', $tagdata);
                }
            }
        }

        /*----------------------------------------
         Parse single variables
        ----------------------------------------*/

        foreach (ee()->TMPL->var_single as $key => $val)
        {
            /*
            if ($key == 'metar')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['metar'],
                                                    $tagdata
                                                    );
            }
            */

			if (strpos($key, 'gmt_last_update') !== FALSE)
            {
				$tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    ee()->localize->format_date($val, $tags['last_update'], FALSE),
                                                    $tagdata
                                                    );
            }


			if (strpos($key,'last_update') !== FALSE)
            {
				$tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    ee()->localize->format_date($val, $tags['last_update']),
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_direction')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_direction'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_degrees')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_degrees'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_speed_mph')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_speed_mph'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_speed_kmh')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_speed_kmh'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_speed_kt')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_speed_kt'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_gust_mph')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_gust_mph'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_gust_kmh')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_gust_kmh'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'wind_gust_kt')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['wind_gust_kt'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'temperature_f')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['temperature_f'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'temperature_c')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['temperature_c'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'feels_like_f')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['feels_like_f'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'feels_like_c')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['feels_like_c'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'dew_point_f')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['dew_point_f'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'dew_point_c')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['dew_point_c'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'humidity')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['humidity'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'heat_index_f')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['heat_index_f'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'heat_index_c')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['heat_index_c'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'barometer_in')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['barometer_in'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'barometer_hpa')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['barometer_hpa'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'condition')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['condition'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'visibility_mi')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['visibility_mi'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'visibility_km')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['visibility_km'],
                                                    $tagdata
                                                    );
            }

            if ($key == 'sky_condition')
            {
                $tagdata = ee()->TMPL->swap_var_single(
                                                    $key,
                                                    $tags['sky_condition'],
                                                    $tagdata
                                                    );
            }

        }
        //END single variables

        return $tagdata;;
    }

	// --------------------------------------------------------------------

	/**
	* Valid ICAO
	*
	* @access   public
	* @param    string
	* @return   boolean
	*/
    function valid_icao($icao)
    {
        if (ctype_alpha($icao) && (strlen($icao) == 4))
        {
            return true;
        }
        return false;
    }

	// --------------------------------------------------------------------

	/**
	* Receive Socket
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    number
	* @return   boolean
	*/
    function retrieve_data_socket($host, $path, $timeout = 30)
    {
        $fp = fsockopen("ssl://".$host, 443, $errno, $errstr, $timeout);

        if ( ! is_resource($fp))
        {
            return false;
        }

        $request  = "GET ".$path." HTTP/1.0\r\n";
        $request .= "Host: ".$host."\r\n";
        $request .= "User-Agent: PHP/".phpversion()."\r\n\r\n";
        $request .= "Connection: Close\r\n\r\n";

        fputs($fp, $request);

        $this->cache_data = '';

        $getting_headers = true;

        while ( ! feof($fp))
        {
            $line = fgets($fp, 1024);

            if ($getting_headers == false)
            {
                $this->cache_data .= $line;
            }
            elseif (trim($line) == '')
            {
                $getting_headers = false;
            }
        }

        fclose($fp);

        if (trim($this->cache_data) == '')
        {
            return false;
        }

        $this->write_cache();

        return true;
    }

	// --------------------------------------------------------------------

	/**
	* Write Cache
	*
	* @access   public
	* @return   boolean
	*/
    function write_cache()
    {
        if ( ! @is_dir($this->cache_dir))
        {
            if ( ! @mkdir($this->cache_dir, 0777))
            {
                return false;
            }

            @chmod($this->cache_dir, 0777);
        }

        $fp = @fopen($this->cache_tpath, 'wb');
        $sp = @fopen($this->cache_path, 'wb');

        if (( ! is_resource($fp)) || ( ! is_resource($sp)))
        {
                return false;
        }

        flock($fp, LOCK_EX);
        flock($sp, LOCK_EX);

        @fwrite($fp, time());
        @fwrite($sp, $this->cache_data);

        flock($fp, LOCK_UN);
        flock($sp, LOCK_UN);

        fclose($fp);
        fclose($sp);

        return true;
    }

	// --------------------------------------------------------------------

	/**
	* Get Cache
	*
	* Function description
	*
	* @access   public
	* @return   boolean
	*/
    function fetch_cache()
    {
        if (( ! file_exists($this->cache_path)) || ( ! file_exists($this->cache_tpath)))
        {
            return false;
        }

        $fp = @fopen($this->cache_tpath, 'rb');
        $sp = @fopen($this->cache_path, 'rb');

        if (is_resource($fp) || is_resource($sp))
        {
            flock($fp, LOCK_SH);
            $timestamp = trim(@fread($fp, filesize($this->cache_tpath)));
            flock($fp, LOCK_UN);
            fclose($fp);
            if ((($this->cache_refresh * 60) + $timestamp) > time())
            {
                flock($sp, LOCK_SH);
                $this->cache_data = trim(@fread($sp, filesize($this->cache_path)));
                flock($sp, LOCK_UN);
                fclose($sp);
                return true;
            }
        }

        return false;
    }

	// --------------------------------------------------------------------

	/**
	* Get filetime
	*
	* Gets file modification time: returns it as GMT
	*
	* @access   public
	* @return   string
	*/
    function get_filemtime()
    {
        if (file_exists($this->cache_path))
        {
            if ($mtime = @filemtime($this->cache_path))
            {
				//return $mtime;

				$time = gmmktime(
                                gmdate("H", $mtime),
                                gmdate("i", $mtime),
                                gmdate("s", $mtime),
                                gmdate("m", $mtime),
                                gmdate("d", $mtime),
                                gmdate("Y", $mtime)
                                );
                return $time;

            }
        }

        return;
    }

	// --------------------------------------------------------------------

	/**
	* Parse metar data
	*
	* @access   public
	* @return   array
	*/
    function parse_metar_data()
    {
        /*---------------------------------------
         Parsing Patterns
        -----------------------------------------*/

        $regex = array(
                        "station"     => "[a-zA-Z]{4}",
                        "update"      => "(\d{2})(\d{2})(\d{2})Z",
                        "modifier"    => "AUTO|COR",
                        "wind"        => "(\d{3}|VAR|VRB)(\d{2,3})(G(\d{2}))?(KT|MPS|KMH)",
                        "windVar"     => "(\d{3})V(\d{3})",
                        "visibility1" => "\d",
                        "visibility2" => "(?:((\d{4})([NS]?[EW]?))|((?:M?(?:(\d{1,2})|(?:(\d)(\/)(\d))))(SM|KM))|(CAVOK))",
                        "condition"  => "(VC)?(\-|\+)?(MI|PR|BC|DR|BL|SH|TS|FZ)?(DZ|RA|SN|SG|IC|PL|GR|GS|UP)?".
                                         "(BR|FG|FU|VA|DU|SA|HZ|PY)?(PO|SQ|FC|SS|DS)?",
                        "clouds"      => "(VV|SKC|SKT|CLR|FEW|NSC|SCT|BKN|OVC)(\d{3}|\/\/\/)?(CB|TCU)?",
                        "temperature" => "(M?\d{2})\/(M?\d{2}|XX|\/\/)?",
                        "pressure"    => "(A|Q)(\d{4})",
                        "nosig"       => "NOSIG",
                        "remark"      => "RMK"
                        );

        /*---------------------------------------
         Parse the Metar
        -----------------------------------------*/

        // remove extra white space
        $raw_metar = trim(preg_replace('/([\r\n])*[\s]+/', ' ', $this->cache_data));

        $weather = array();

        // get date: 2004/08/08 23:00
        if (preg_match("/(20\d{2})\/([0-5]\d)\/(\d{2}) (\d{2}):(\d{2})/", $raw_metar, $date))
        {
            $weather['last_update'] = gmmktime(
                                            $date[4], // hour
                                            $date[5], // min
                                            0,        //
                                            $date[2], // month
                                            $date[3], // day
                                            $date[1]  // year
                                            );


        }

        $metar_groups = explode(' ', $raw_metar);

        $group_count = count($metar_groups);

        for ($i = 0; $i < $group_count; $i++)
        {
            foreach ($regex as $key => $regexp)
            {
                if (preg_match("/^".$regexp."$/", $metar_groups[$i], $parts))
                {
                    switch ($key)
                    {
                        // Wind Group
                        case 'wind':
                            switch ($parts[1])
                            {
                                // Calm Winds
                                case '0000':
                                case '00000':
                                    $weather['wind_direction'] = lang('calm');
                                    $weather['wind_degrees']   = lang('calm');
                                    break;

                                // Variable Winds
                                case 'VAR':
                                case 'VRB':
                                    $weather['wind_direction'] = lang('variable');
                                    $weather['wind_degrees']   = lang('variable');
                                    break;

                                // Default
                                default:
                                    $weather['wind_direction'] = $this->degrees2compass($parts[1]);
                                    $weather['wind_degrees']   = $parts[1];
                                    break;
                            }

                            // Wind Speed
                            $weather['wind_speed_mph'] = $this->convert_speed($parts[2], $parts[5], 'mph');
                            $weather['wind_speed_kt']  = $this->convert_speed($parts[2], $parts[5], 'kt');
                            $weather['wind_speed_kmh'] = $this->convert_speed($parts[2], $parts[5], 'kmh');

                            // Wind Gust
                            if (is_numeric($parts[4]))
                            {
                                $weather['wind_gust_mph'] = $this->convert_speed($parts[4], $parts[5], 'mph');
                                $weather['wind_gust_kt']  = $this->convert_speed($parts[4], $parts[5], 'kt');
                                $weather['wind_gust_kmh'] = $this->convert_speed($parts[4], $parts[5], 'kmh');
                            }
                            unset($regex[$key]);
                            break;

                        // Visibility1
                        case 'visibility1':
                            $whole_mile = $parts[0];
                            unset($regex[$key]);
                            break;

                        // Visibility2
                        case "visibility2":
                            $whole_mile = ( ! isset($whole_mile)) ? '' : $whole_mile;

                             // Visibility in Meters
                            if (count($parts) <= 4)
                            {
                                switch ($parts[2])
                                {
                                    // specail low
                                    case '0000':
                                        $prefix = lang('less_than');
                                        $weather['visibility_km']  = $prefix . ' ' . 0.05;
                                        $weather['visibility_mi']  = $prefix . ' ' . 0.031;
                                        break;

                                    // specail high
                                    case '9999':
                                        $prefix = lang('greater_than');
                                        $weather['visibility_km']  = $prefix . ' ' . 10;
                                        $weather['visibility_mi']  = $prefix . ' ' . 6.2;
                                        break;

                                    // normal
                                    default:
                                        $weather['visibility_km']  = $this->convert_distance($parts[2], 'm', 'km', 1);
                                        $weather['visibility_mi']  = $this->convert_distance($parts[2], 'm', 'sm', 1);
                                        break;
                                 }
                            }
                            // Visibility in Miles
                            elseif (count($parts) == 10)
                            {
                                $prefix = ($parts[0]{0} != 'M') ? '' : lang('less_than');

                                // whole number and fraction
                                if ($parts[7] == '/')
                                {
                                    $distance = $whole_mile + ($parts[6] / $parts[8]);

                                    $weather['visibility_km']  = $prefix.' '.$this->convert_distance($distance, 'sm', 'km', 1);
                                    $weather['visibility_mi']  = $prefix.' '.$this->convert_distance($distance, 'sm', 'sm', 1);
                                }
                                else
                                {
                                    // whole number
                                    switch ($parts[9])
                                    {
                                        // mile
                                        case 'SM':
                                            $weather['visibility_km']  = $prefix.' '.
                                                                         $this->convert_distance($parts[5], 'sm', 'km', 1);
                                            $weather['visibility_mi']  = $prefix.' '.
                                                                         $this->convert_distance($parts[5], 'sm', 'sm', 1);
                                            break;

                                        // kilometers
                                        case 'KM':
                                            $weather['visibility_km']  = $prefix.' '.
                                                                         $this->convert_distance($parts[5], 'km', 'km', 1);
                                            $weather['visibility_mi']  = $prefix.' '.
                                                                         $this->convert_distance($parts[5], 'km', 'sm', 1);
                                            break;
                                    }
                                }
                            }
                            // CAVOK
                            elseif ($parts[10] == 'CAVOK')
                            {
                                $prefix = lang('greater_than');
                                $weather['visibility_km']  = $prefix . ' ' . 10;
                                $weather['visibility_mi']  = $prefix . ' ' . 6.2;

                                // clouds
                                $weather['sky_condition'] = $this->translate_clouds('SKC');

                                // condition
                                $weather['condition'] = lang('clear');
                            }
                            unset($whole_mile);
                            break;

                        // Conditions
                        case 'condition':

                            $weather['condition'] = ( ! isset($weather['condition'])) ? '' : $weather['condition'].',';

                            for ($j = 1; $j <= count($parts); $j++)
                            {
                                $weather['condition'] .= (empty($parts[$j])) ? '' : lang($parts[$j]);

                            }

                            $weather['condition'] = trim($weather['condition']);
                            break;

                        // Clouds
                        case 'clouds':
                            /*-----------------------------
                              May appear more than once in the metar.
                              Since we only want the last occurrence,
                              we simply let each over write the other.

                              May add further coding to this group in the future,
                              which is the reason for the switch statement.
                            -------------------------------*/
                            if (isset($parts[2]))
                            {
                                switch ($parts[2])
                                {
                                    // spacial value
                                    case '000':
                                        $weather['sky_condition'] = $this->translate_clouds($parts[1]);
                                        break;

                                    // below station level/unknown
                                    case '///':
                                        break;

                                    // default
                                    default:
                                        $weather['sky_condition'] = $this->translate_clouds($parts[1]);
                                        break;
                                }
                            }
                            else
                            {
                                $weather['sky_condition'] = $this->translate_clouds($parts[1]);
                            }
                            break;

                        // Temperature
                        case 'temperature':

                            $tempC = strtr($parts[1], 'M', '-');

                            // temperature
                            $tempC = $this->convert_temperature($tempC, "c", "c");
                            $tempF = $this->convert_temperature($tempC, "c", "f");

                            $weather['temperature_c'] = $tempC;
                            $weather['temperature_f'] = $tempF;

                            // feels like
                            if (isset($weather['wind_speed_mph']))
                            {
                                $weather['feels_like_f'] = $this->calc_wind_chill($tempF, $weather['wind_speed_mph']);
                                $weather['feels_like_c'] = $this->convert_temperature($weather['feels_like_f'], "f", "c");
                            }

                            // dew point
                            if (is_numeric($parts[2]))
                            {
                                $dewC = strtr($parts[2], 'M', '-');

                                $dewC = $this->convert_temperature($dewC, "c", "c");
                                $dewF = $this->convert_temperature($dewC, "c", "f");

                                $weather['dew_point_c'] = $dewC;
                                $weather['dew_point_f'] = $dewF;

                                // relative humidity
                                $weather['humidity'] = $this->calc_humidity($tempC, $dewC, 1);

                                // heat index
                                $weather['heat_index_f'] = $this->calc_heat_index($tempF, $weather['humidity']);
                                $weather['heat_index_c'] = $this->convert_temperature($weather['heat_index_f'], "f", "c");
                            }

                            unset($regex[$key]);
                            break;

                        // Pressure
                        case 'pressure':
                            if ($parts[1] == 'A')
                            {
                                $weather['barometer_in']  = ($parts[2] / 100);
                                $weather['barometer_hpa'] = $this->convert_pressure(($parts[2] / 100), 'in', 'hpa');
                            }
                            elseif ($parts[1] == 'Q')
                            {
                                $weather['barometer_in']  = $this->convert_pressure($parts[2], 'hpa', 'in', 2);
                                $weather['barometer_hpa'] = $parts[2];
                            }
                            unset($regex[$key]);
                            break;

                        // Remarks
                        case 'remark':
                            /*-----------------------------
                              Not going to decode remarks, so
                              we'll stop parsing
                            -------------------------------*/
                            break 3;

                            // Do nothing, just prevent further matching
                            default:
                                unset($parts[$key]);
                                break;
                    }
                    //End Switch
                }
                //End If
            }
            //End Foreach
        }
        //End For

        $this->weather_data = $weather;
    }

	// --------------------------------------------------------------------

	/**
	* Translate Clouds
	*
	* CONVERSION AND CALCULATION METHODS
	*
	* @access   public
	* @param    string
	* @return   string
	*/
    function translate_clouds($code)
    {
        switch ($code)
        {
            case 'SKC':
            case 'NSC':
                $translation = lang('clear_skies');
                break;

            case 'CLR':
                $translation = lang('clear_below_twelve_thousand_feet');
                break;

            case 'FEW':
                $translation = lang('scattered_clouds');
                break;

            case 'SCT':
            case 'SKT':
                $translation = lang('partly_cloudy');
                break;

            case 'BKN':
                $translation = lang('mostly_cloudy');
                break;

            case 'OVC':
                $translation = lang('overcast');
                break;

            case 'VV':
                $translation = lang('obscured');
                break;

            case 'CB':
                $translation = lang('cumulonimbus');
                break;

            case 'TCU':
                $translation = lang('towering_cumulus');
                break;

            default:
                $translation = lang('unknown');
                break;
        }
        return ($translation);
    }

	// --------------------------------------------------------------------

	/**
	* Degrees2Compass
	*
	* CONVERSION AND CALCULATION METHODS
	* Convert degrees to compass
	*
	* @access   public
	* @param    string
	* @return   number
	*/
    function degrees2compass($direction)
    {
        $compass = array(
                            lang('north'),
                            lang('north_north_east'),
                            lang('north_east'),
                            lang('east_north_east'),
                            lang('east'),
                            lang('east_south_east'),
                            lang('south_east'),
                            lang('south_south_east'),
                            lang('south'),
                            lang('south_south_west'),
                            lang('south_west'),
                            lang('west_south_west'),
                            lang('west'),
                            lang('west_north_west'),
                            lang('north_west'),
                            lang('north_north_west'),
                        );

        return $compass[ round($direction / 22.5) % 16 ];
    }

	// --------------------------------------------------------------------

	/**
	* Convert temperature
	*
	* CONVERSION AND CALCULATION METHODS
	* Convert temperature between f and c
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    string
	* @param    string
	* @return   number
	*/
    function convert_temperature($temperature, $from, $to, $precision = 0)
    {
        $from = strtolower($from{0});
        $to   = strtolower($to{0});

        $result = array (
            "f" => array(
                "f" => $temperature,            "c" => ($temperature - 32) / 1.8
            ),
            "c" => array(
                "f" => 1.8 * $temperature + 32, "c" => $temperature
            )
        );

        return round($result[$from][$to], $precision);
    }

	// --------------------------------------------------------------------

	/**
	* Convert speed
	*
	* CONVERSION AND CALCULATION METHODS
	* Convert speed between mph, kmh, kt, mps and fps
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    string
	* @param    number
	* @return   number
	*/
    function convert_speed($speed, $from, $to, $precision = 0)
    {
        $from = strtolower($from);
        $to   = strtolower($to);

        static $factor;

        if (!isset($factor))
        {
            $factor = array(
                "mph" => array(
                    "mph" => 1,         "kmh" => 1.609344, "kt" => 0.8689762, "mps" => 0.44704,   "fps" => 1.4666667
                ),
                "kmh" => array(
                    "mph" => 0.6213712, "kmh" => 1,        "kt" => 0.5399568, "mps" => 0.2777778, "fps" => 0.9113444
                ),
                "kt"  => array(
                    "mph" => 1.1507794, "kmh" => 1.852,    "kt" => 1,         "mps" => 0.5144444, "fps" => 1.6878099
                ),
                "mps" => array(
                    "mph" => 2.2369363, "kmh" => 3.6,      "kt" => 1.9438445, "mps" => 1,         "fps" => 3.2808399
                ),
                "fps" => array(
                    "mph" => 0.6818182, "kmh" => 1.09728,  "kt" => 0.5924838, "mps" => 0.3048,    "fps" => 1
                )
            );
        }

        return round($speed * $factor[$from][$to], $precision);
    }

	// --------------------------------------------------------------------

	/**
	* Convert pressure
	*
	* CONVERSION AND CALCULATION METHODS
	* Convert pressure between in, hpa, mb, mm and atm
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    string
	* @param    number
	* @return   number
	*/
    function convert_pressure($pressure, $from, $to, $precision = 0)
    {
        $from = strtolower($from);
        $to   = strtolower($to);

        static $factor;

        if (!isset($factor))
        {
            $factor = array(
                "in"   => array(
                    "in" => 1,         "hpa" => 33.863887, "mb" => 33.863887, "mm" => 25.4,      "atm" => 0.0334213
                ),
                "hpa"  => array(
                    "in" => 0.02953,   "hpa" => 1,         "mb" => 1,         "mm" => 0.7500616, "atm" => 0.0009869
                ),
                "mb"   => array(
                    "in" => 0.02953,   "hpa" => 1,         "mb" => 1,         "mm" => 0.7500616, "atm" => 0.0009869
                ),
                "mm"   => array(
                    "in" => 0.0393701, "hpa" => 1.3332239, "mb" => 1.3332239, "mm" => 1,         "atm" => 0.0013158
                ),
                "atm"  => array(
                    "in" => 29,921258, "hpa" => 1013.2501, "mb" => 1013.2501, "mm" => 759.999952, "atm" => 1
                )
            );
        }

        return round($pressure * $factor[$from][$to], $precision);
    }

	// --------------------------------------------------------------------

	/**
	* Convert distance
	*
	* CONVERSION AND CALCULATION METHODS
	* Convert distance and length between km, ft, sm, and m
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    string
	* @param    number
	* @return   number
	*/
    function convert_distance($distance, $from, $to, $precision = 0)
    {
        $to   = strtolower($to);
        $from = strtolower($from);

        static $factor;

        if (!isset($factor))
        {
            $factor = array(
                "km" => array(
                    "km" => 1,         "ft" => 3280.839895, "sm" => 0.6213699, "m" => 1000
                ),
                "ft" => array(
                    "km" => 0.0003048, "ft" => 1,           "sm" => 0.0001894, "m" => 0.3048
                ),
                "sm" => array(
                    "km" => 1.6093472, "ft" => 5280.0106,   "sm" => 1,         "m" => 1609.344
                ),
                "m" => array(
                    "km" => 0.001,     "ft" => 3.2808399,   "sm" => 0.0006214, "m" => 1
                )
            );
        }

        return round($distance * $factor[$from][$to], $precision);
    }

	// --------------------------------------------------------------------

	/**
	* Convert wind chill
	*
	* CONVERSION AND CALCULATION METHODS
	* Calculate windchill from temperature and windspeed
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    number
	* @return   number
	*/
    function calc_wind_chill($temperature_f, $speed_mph, $precision = 0)
    {
        return round(35.74 + 0.6215 * $temperature_f - 35.75 * pow($speed_mph, 0.16) + 0.4275 * $temperature_f * pow($speed_mph, 0.16));
    }

	// --------------------------------------------------------------------

	/**
	* Convert humidity
	*
	* CONVERSION AND CALCULATION METHODS
	* Calculate humidity from temperature and dewpoint
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    number
	* @return   number
	*/
    function calc_humidity($temperature_c, $dew_point_c, $precision = 0)
    {
        $humidity = 100 * pow((112 - (0.1 * $temperature_c) + $dew_point_c) /
                    (112 + (0.9 * $temperature_c)), 8);

        return round($humidity, $precision);
    }

	// --------------------------------------------------------------------

	/**
	* Heat index
	*
	* CONVERSION AND CALCULATION METHODS
	* Calculate Heat Index. returns in Fahrenheit
	*
	* @access   public
	* @param    string
	* @param    string
	* @param    number
	* @return   array
	*/
    function calc_heat_index($temperature_f, $humidity, $precision = 0)
    {
        return number_format(
                            -42.379 + 2.04901523 * $temperature_f
                            + 10.1433312 * $humidity
                            - 0.22475541 * $temperature_f * $humidity
                            - 0.00683783 * $temperature_f * $temperature_f
                            - 0.05481717 * $humidity * $humidity
                            + 0.00122874 * $temperature_f * $temperature_f * $humidity
                            + 0.00085282 * $temperature_f * $humidity * $humidity
                            - 0.00000199 * $temperature_f * $temperature_f * $humidity * $humidity,
                            $precision
                            );
	}

	// --------------------------------------------------------------------
}
