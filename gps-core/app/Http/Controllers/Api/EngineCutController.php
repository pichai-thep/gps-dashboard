<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EngineCutController extends Controller
{
    private function resolveCommandServerIp(?string $dbConnection): string
    {
        $serverIp = null;

        if ($dbConnection) {
            $serverIp = config("database.connections.$dbConnection.host");
        }

        $serverIp = $serverIp ?: \Request::get('server_ip');

        if (!$serverIp) {
            throw new \Exception("command server ip not found for db connection: $dbConnection");
        }

        return $serverIp;
    }

    public function index(Request $request){
        $status_code = 200;
        $code = "1";
        $msg = "OK";

        return response()->json(array(
            'code' => $code,
            'message' => $msg,
        ),$status_code);
    }

    public function send_to_socket($dbConn, $username, $imei, $server_ip, $port, $data){
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            Log::debug("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
        }
        $result = socket_connect($socket, $server_ip, $port);
        if ($result === false) {
            Log::debug("socket_connect() $server_ip:$port failed.\nReason: " . socket_strerror(socket_last_error($socket)));
        }
        $len = strlen($data);
        socket_send($socket, $data, $len, MSG_DONTROUTE);
        socket_close($socket);
        Log::debug("Send command: $data for imei: $imei to server ip: $server_ip:$port success");
    }

    public function engine_cut(Request $request){

        $dbConnection = $request->attributes->get('gps_connection');

        $status_code = 200;
        $server = \Request::get('server');
        $server_ip = null;
        $username = \Request::get('username');

        $customer_id = $request->input('customer_id');
        $imei = $request->input('imei');
        $pwd = $request->input('pwd','');
        $ref_id = '';

        try{
            $server_ip = $this->resolveCommandServerIp($dbConnection);
            Log::debug("Engine cut========> dbConnection=$dbConnection, server_ip=$server_ip, customer_id=$customer_id,imei=$imei,pwd=$pwd");

            $engine_cut_pwd = Customer::on($dbConnection)->where('customer_id', $customer_id)->value('engine_cut_pwd');
            Log::debug("Engine-cut Pwd: $engine_cut_pwd");

            if ($engine_cut_pwd!=null){
                if ($engine_cut_pwd!= $pwd) {
                    throw new \Exception("wrong password, command not authorized");
                }
            }

            $tracker = Tracker::on($dbConnection)->where('imei','=',$imei)->first();
            if ($tracker==null){
                throw new \Exception("not found tracker imei: $imei");
            }
            $dev_model = $tracker->tracker_model;
            $dev_pwd = $tracker->device_pwd;

            switch ($dev_model){
                case "meitrack":
                case "T1":
                case "T333":
                    $this->cut_meitrack($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Totemtech":
                case "Totem-107-2G":
                case "Totem-107-3G":
                case "Totem-107-4G":
                    $this->cut_totem_107($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Totem-09":
                case "Totem-109-3G":
                case "Totem-109-4G":
                    $this->cut_totem_109($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Ruptela":
                    $this->cut_ruptela($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Concox":
                    $this->cut_concox($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "FiFoTrack":
                    $this->cut_fifo($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "iStartek":
                    $this->cut_istartek($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "jm-evl":
                    $this->cut_jm_evl($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;

            }

            $code = 1;
            $msg = 'ok';
            $ref_id = Carbon::now()->getTimestamp();
        }
        catch(\Throwable $ex){
            $code = 0;
            $msg = $ex->getMessage();
        }finally {
            Log::debug("Engine-cut resp code: $code msg: $msg ref_id: $ref_id");
            return response()->json(array(
                'code' => $code,
                'message' => $msg,
                'server_ip' => $server_ip,
                'ref_id' => $ref_id,
            ), $status_code);
        }
    }

    public function cut_meitrack($conn, $server, $username, $imei, $dev_pwd){

//        0000,C01,0,12222  --> สั่งดับ
//        0000,C01,0,02222   --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("MEITRACK_CMD_PORT", 31201);
        $cmd_engine_cut = 'C01,20,12222';

        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_meitrack command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_totem_107($conn, $server, $username, $imei, $dev_pwd){

        $server_ip = $this->resolveCommandServerIp($conn);
//        $server_ip = $server;
        $cmdPort = env("TOTEM_07_CMD_PORT", 31501);

//        $$<length><CF><******,Command_content><checksum>
//        $$0029CF000000,052,1234567831

//        *792544,550,20# --> กำหนดความเร็วดับเครื่อง
//        *792544,130,1# --> ปรับโหมดดับเครื่อง
//        *792544,016,A,1# --> สั่งดับ
//        *792544,016,A,0# --> ยกเลิกดับ

        $cmd_engine_cut = sprintf('%s,016,A,1', $dev_pwd);
        $cmd_interval_mode =  sprintf('%s,102,30,0,0,30', $dev_pwd);
//        $cmd_output_mode =  sprintf('%s,130,1', $dev_pwd);

        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_totem_107 command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_totem_109($conn, $server, $username, $imei, $dev_pwd){

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("TOTEM_09_CMD_PORT", 31601);

//        $$<length><CF><******,Command_content><checksum>
//        $$0029CF000000,052,1234567831

//        *792544,550,20#  --> กำหนดความเร็วดับเครื่อง
//        *792544,132,1# --> ปรับโหมดดับเครื่อง
//        *792544,016,C,1# --> สั่งดับ
//        *792544,016,C,0#  --> ยกเลิกดับ

        $cmd_interval_mode =  sprintf('%s,102,30,0,0,30', $dev_pwd);
        $cmd_output_mode =  sprintf('%s,132,1', $dev_pwd);
        $cmd_engine_cut = sprintf('%s,016,C,1', $dev_pwd);

        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_totem_109 command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_ruptela($conn, $server, $username, $imei, $dev_pwd){

//        setcfg 246 2  --> สั่งดับ
//        setcfg 246 0  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("RUPTELA_CMD_PORT", 31701);
        $cmd_engine_cut =  'setcfg 246 2';
//        $cmd_engine_cut =  'setio 406,0';
        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_ruptela command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_concox($conn, $server, $username, $imei, $dev_pwd){

//        RELAY,1#  --> สั่งดับ
//        RELAY,0#  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("CONCOX_CMD_PORT", 31801);
        $cmd_interval_mode =  'TIMER,30,30#';
        $cmd_engine_cut = 'RELAY,1#';

        $data = "$imei|engine-cut|$cmd_engine_cut" ;


        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_concox command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_jm_evl($conn, $server, $username, $imei, $dev_pwd){

//        RELAY,1#  --> สั่งดับ
//        RELAY,0#  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("JM_EVL_CMD_PORT", 33601);
        $cmd_interval_mode =  'TIMER,30,30#';
        $cmd_engine_cut = 'RELAY,1#';

        $data = "$imei|engine-cut|$cmd_engine_cut" ;


        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_jm_evl command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_fifo($conn, $server, $username, $imei, $dev_pwd){

//        000000,B12,1,1,20  --> สั่งดับ
//        000000,B12,1,0,0  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("FIFO_CMD_PORT", 32001);
//        $cmd_engine_cut = sprintf('%s,B12,1,1,20', $dev_pwd);
        $cmd_engine_cut = sprintf('B12,1,1,20');

        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_fifo command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_istartek($conn, $server, $username, $imei, $dev_pwd){

//        0000,900,1,1,0,10 --> คำสั่งดับครับ
//        0000,900,1,0,0,10 --> คำสั่งยกเลิก
//        ความเร็วต่ำกว่า 10 ถึงจะดับ หากเป็น 0 จะดับทันทีไม่สนความเร็ว

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("ISTARTEK_CMD_PORT", 32201);
//        $cmd_engine_cut = sprintf('%s,900,1,1,0,10', $dev_pwd);
        $cmd_engine_cut = '900,1,1,0,10';
        $data = "$imei|engine-cut|$cmd_engine_cut" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_istartek command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }


    // ========================================CANCEL==========================================

    public function engine_cut_cancel(Request $request){

        $status_code = 200;
        $server = \Request::get('server');
        $dbConnection = $request->attributes->get('gps_connection');
        $server_ip = null;
        $username = \Request::get('username');

        $customer_id = $request->input('customer_id');
        $imei = $request->input('imei');
        $pwd = $request->input('pwd');
        $ref_id = '';

        try{
            $server_ip = $this->resolveCommandServerIp($dbConnection);
            Log::debug("Engine cut cancel ========> dbConnection=$dbConnection, server_ip=$server_ip, customer_id=$customer_id,imei=$imei,pwd=$pwd");

            $customer = Customer::on($dbConnection)->find($customer_id);
            Log::debug("engine_cut_cancel customer pwd=$customer->engine_cut_pwd");
            if ($customer!=null){
                if ($customer->engine_cut_pwd!=null) {
                    if ($customer->engine_cut_pwd != $pwd) {
                        throw new \Exception("wrong password, command not authorized");
                    }
                }
            }else{
                Log::debug("engine_cut_cancel customer:$customer_id not found");
            }

            $tracker = Tracker::on($dbConnection)->where('imei','=',$imei)->first();
            if ($tracker==null){
                throw new \Exception("not found tracker imei: $imei");
            }
            $dev_model = $tracker->tracker_model;
            $dev_pwd = $tracker->device_pwd;

            switch ($dev_model){
                case "meitrack":
                case "T1":
                case "T333":
                    $this->cut_cancel_meitrack($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Totemtech":
                case "Totem-107-2G":
                case "Totem-107-3G":
                case "Totem-107-4G":
                    $this->cut_cancel_totem_107($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Totem-09":
                case "Totem-109-3G":
                case "Totem-109-4G":
                    $this->cut_cancel_totem_109($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Ruptela":
                    $this->cut_cancel_ruptela($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "Concox":
                    $this->cut_cancel_concox($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "FiFoTrack":
                    $this->cut_cancel_fifo($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "iStartek":
                    $this->cut_cancel_istartek($dbConnection, $server, $username, $imei, $dev_pwd);
                    break;
                case "jm-evl":
                    $this->cut_cancel_jm_evl($dbConnection, $server, $username, $imei, $dev_pwd);
            }

            $code = 1;
            $msg = 'ok';
            $ref_id = Carbon::now()->getTimestamp();
        }
        catch(\Exception $ex){
            $code = 0;
            $msg = $ex->getMessage();
        }catch(\Throwable $ex){
            $code = 0;
            $msg = $ex->getMessage();
        }finally {
            Log::debug("Engine-cut-cancel resp code: $code msg: $msg ref_id: $ref_id");
            return response()->json(array(
                'code' => $code,
                'message' => $msg,
                'ref_id' => $ref_id ?? null,
            ), $status_code);
        }
    }

    public function cut_cancel_meitrack($conn, $server, $username, $imei, $dev_pwd){

//        0000,C01,0,12222  --> สั่งดับ
//        0000,C01,0,02222   --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("MEITRACK_CMD_PORT", 31201);
        $cmd_engine_cut_cancel = 'C01,0,02222';
        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_meitrack command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_totem_107($conn, $server, $username, $imei, $dev_pwd){

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("TOTEM_07_CMD_PORT", 31501);

//        $$<length><CF><******,Command_content><checksum>
//        $$0029CF000000,052,1234567831

//        *792544,550,20# --> กำหนดความเร็วดับเครื่อง
//        *792544,130,1# --> ปรับโหมดดับเครื่อง
//        *792544,016,A,1# --> สั่งดับ
//        *792544,016,A,0# --> ยกเลิกดับ

        $cmd_engine_cut_cancel = sprintf('%s,016,A,0', $dev_pwd);
        $cmd_interval_mode =  sprintf('%s,102,60,0,0,600', $dev_pwd);

        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_totem_107 command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_totem_109($conn, $server, $username, $imei, $dev_pwd){

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("TOTEM_09_CMD_PORT", 31601);

//        $$<length><CF><******,Command_content><checksum>
//        $$0029CF000000,052,1234567831

//        *792544,550,20#  --> กำหนดความเร็วดับเครื่อง
//        *792544,132,1# --> ปรับโหมดดับเครื่อง
//        *792544,016,C,1# --> สั่งดับ
//        *792544,016,C,0#  --> ยกเลิกดับ

        $cmd_engine_cut_cancel = sprintf('%s,016,C,0', $dev_pwd);
        $cmd_interval_mode =  sprintf('%s,102,60,0,0,600', $dev_pwd);

        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_totem_109 command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_ruptela($conn, $server, $username, $imei, $dev_pwd){

//        setcfg 246 2  --> สั่งดับ
//        setcfg 246 0  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("RUPTELA_CMD_PORT", 31701);
        $cmd_engine_cut_cancel =  'setcfg 246 0';
        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_ruptela command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_concox($conn, $server, $username, $imei, $dev_pwd){

//        RELAY,1#  --> สั่งดับ
//        RELAY,0#  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("CONCOX_CMD_PORT", 31801);
        $cmd_sendmode =  'TIMER,60,600#';
        $cmd_engine_cut_cancel = 'RELAY,0#';
        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_concox command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_fifo($conn, $server, $username, $imei, $dev_pwd){

//        000000,B12,1,1,20  --> สั่งดับ
//        000000,B12,1,0,0  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("FIFO_CMD_PORT", 32001);
//        $cmd_engine_cut_cancel = sprintf('%s,B12,1,0,0', $dev_pwd);
        $cmd_engine_cut_cancel = 'B12,1,0,0';

        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_fifo command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_istartek($conn, $server, $username, $imei, $dev_pwd){

//        0000,900,1,1,0,10 --> คำสั่งดับครับ
//        0000,900,1,0,0,10 --> คำสั่งยกเลิก
//        ความเร็วต่ำกว่า 10 ถึงจะดับ หากเป็น 0 จะดับทันทีไม่สนความเร็ว

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("ISTARTEK_CMD_PORT", 32201);
//        $cmd_engine_cut_cancel = sprintf('%s,900,1,0,0,10', $dev_pwd);
        $cmd_engine_cut_cancel = '900,1,0,0,10';
        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_istartek command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }

    public function cut_cancel_jm_evl($conn, $server, $username, $imei, $dev_pwd){

//        RELAY,1#  --> สั่งดับ
//        RELAY,0#  --> ยกเลิกดับ

        $server_ip = $this->resolveCommandServerIp($conn);
        $cmdPort = env("JM_EVL_CMD_PORT", 33601);
        $cmd_sendmode =  'TIMER,60,600#';
        $cmd_engine_cut_cancel = 'RELAY,0#';
        $data = "$imei|engine-cut-cancel|$cmd_engine_cut_cancel" ;

        Log::debug("server:$server_ip port:$cmdPort user:$username send cut_cancel_jm_evl command to imei:$imei --> \"$data\"");
        $this->send_to_socket($conn, $username, $imei, $server_ip, $cmdPort, $data);
    }


}
