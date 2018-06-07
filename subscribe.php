<?php
require_once 'init.php';
require_once 'modules/addons/legendsock/class.php';

function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function GetRandStr( $len ) {
    $chars = [
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
        "3", "4", "5", "6", "7", "8", "9"
    ];
    $charsLen = count($chars) - 1;
    shuffle($chars);
    $output = "";
    for ($i=0; $i<$len; $i++) {
        $output .= $chars[mt_rand(0, $charsLen)];
    }
    return $output;
}

if ( $_REQUEST['action'] == 'reSet' ) {
        $newpasswd         = GetRandStr( 12 );
        $sid                 = (int) $_REQUEST['sid'];
        $userid         = (int) $_REQUEST['uid'];
        $data                = \WHMCS\Database\Capsule::table('tblhosting')->where('id', $sid)->first();

        if ( $userid != $data->userid or empty($userid) ) {
                $result = [
                'status'        => 'error',
                'msg'                         => '参数错误',
            ];
        } else {
                $result         = \WHMCS\Database\Capsule::table('tblhosting')->where('id', $sid)->update(['dedicatedip' => $newpasswd]);
                if ( empty( $result ) ) {
                    $result = [
                        'status'        => 'error',
                        'msg'                         => '重置失败',
                    ];
                } else {
                        $result = [
                        'status'        => 'success',
                        'msg'                         => $newpasswd,
                    ];
                }
        }
        echo json_encode($result);die();
}

$sid         = (int) $_GET['sid'];
$token         = $_GET['token'];

$product         = \WHMCS\Database\Capsule::table('tblhosting')->where('dedicatedip', $token)->where('id', $sid)->first();

if ( empty( $product ) ) {
        die('什么也没输出');
}

if ( $token == $product->dedicatedip ) {

        $hosts = \WHMCS\Database\Capsule::table('tblproducts')->where('id', $product->packageid)->first()->configoption12;
        $servers = \WHMCS\Database\Capsule::table('ls_setting')->where('sid', $product->server)->first()->node;

        if ( !empty( $hosts ) ) {
                $hosts = explode(PHP_EOL, $hosts);
        } else {
                if ( !empty( $servers ) ) {
                        $hosts = explode(PHP_EOL, $servers);
                }
        }

        $i = 0;

        foreach ($hosts as $host) {
                list(, $hosts[$i]) = explode('|', $host);
                list($remark[$i]) = explode('|', $host);
                ++$i;
        }

        $i = 0;
        $ls = new \LegendSock\Extended();
        $db = new \LegendSock\Database();
        $data = $ls->getConnect($product->server);
        $getData = $data->runSQL([
                'action' => [
                        'user' => [
                                'sql' => 'SELECT u,d,t,port,obfs,method,protocol,passwd,transfer_enable FROM user WHERE pid = ?',
                                'pre' => [
                                        $product->id
                                ]
                        ]
                ],
                'trans' => false
        ]);

        if (empty($getData['user']['result'])) {
                throw new Exception('无法从数据库中取得当前产品的信息，请检查产品是否并未处于开通状态');
        }

        $get = $getData['user']['result'];

        $output = array();

        foreach ($hosts as $host) {
                $temp['remark']         = $remark[$i++];
                $temp['port']                 = $get['port'];
                $temp['hostname']         = $host;
                $temp['password']         = $get['passwd'];
                $temp['obfs']                 = $get['obfs'];
                $temp['method']         = $get['method'];
                $temp['protocol']         = $get['protocol'];
                array_push($output, $temp);
        }

        $text = '';
        foreach ($output as $val) {
                $code = $val['hostname'] . ':' . $val['port'] . ':' . $val['protocol'] . ':' . $val['method'] . ':' . $val['obfs'] . ':' . base64_encode($temp['password']);
                $result .= 'ssr://' . base64url_encode( $code . '/?obfsparam=&remarks=' . base64url_encode($val['remark']) . '&group='.base64url_encode($GLOBALS['CONFIG']['CompanyName']).'&udpport=0&uot=0'). PHP_EOL;
        }
        if ( $product->domainstatus == 'Active' ) {
                exit(base64_encode($result));
        }
}
