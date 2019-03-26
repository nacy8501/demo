<?php
/******************************
 * $File:trust.cash.php
 * $Description:资金托管提现
******************************/
if (! defined ( 'OS' ) || OS != 'DYOS') die ( 'System Access Denied' );

if($_U['query_type']=="" || $_U['query_type']=="list"){
    $template = 'users_trust.html';
}

elseif($_U['query_type']=="log" ){
    $template = 'users_trust_cash_log.html';
}

elseif($_U['query_type']=="new"){
    //检查手机宝令是否正确 chenwei
        $_data['user_id'] = $_G['user_id'];
    	$_data['safe_code']	 = $_POST['safe_code'];
        $_data['module']  = 'phone';
        $_data['method']  = 'post';
        $_data['q']       = 'check_safe_code';
        $safe_result = dy_get_server($_data);
        if($safe_result['result'] !='success'){
            echo "<script>alert('{$safe_result['error_remark']}');location.href='/?user&m=trust/cash'</script>";
            exit();
        }

     if(isset($_POST['money'])){
        if ( $_POST['money']=="" || $_POST['money']<100){
    		$msg = array('code'=>'account','msg'=>"提现金额不能小于100");
        }elseif (  $_POST['money']>$_G['account_result']['balance']){
    		$msg = array('code'=>'account','msg'=>"提现金额不能大于".$_G['account_result']['balance']);
    	}elseif (empty($_POST['ajax_phone_code'])){
    	    $msg = array('code'=>'valicode','msg'=>"验证码错误。");
    	}else{
            $var = array("money","type","remark","beizhu","ajax_phone_code");
        	$data = post_var($var);
        	$data['user_id'] = $_G['user_id'];
        	$data['status'] = 0;
            if (!is_numeric($data['money'])){
        		$msg = array('code'=>'account','msg'=>"请输入正确的充值金额");
        	}else{
				$_data['ajax_phone_code'] = $data['ajax_phone_code'];
                $_data['return_url'] = $_data['notify_url']  = $_SERVER['SCRIPT_URI']."?return&module=trust&q=cash";
                $_data['money'] = $_POST['money'];
                $fromUrl = isset($_POST['custWeb'])?$_POST['custWeb']:'';
                $result = dy_get_server(array('user_id'=>$_G['user_id'],'fromUrl'=>$fromUrl,'module'=>'trust','q'=>'cash',
                "method"=>"post","auto"=>1,"target"=>0,"info"=>base64_encode(json_encode($_data))));
                if ($result['result'] == "success") {
                	if($result['data']['result']=='false'){
                		echo "<script>alert('{$result['data']['remark']}');location.href='/?user&m=trust/cash'</script>";
                	}
                    echo urldecode(base64_decode($result['data']['form']));exit;
                } else {
                   echo "<script>alert('{$result['error_remark']}');location.href='/?user&m=trust/cash'</script>";
                }
                exit;
        	}
        }
        echo "<script>alert('{$msg['msg']}');location.href='/?user&m=trust/cash';</script>;";exit;
     }else{
        $template = 'users_trust_cash.html';
     }

}

elseif($_U['query_type'] == 'get_fee'){//wqs 获取充值费用
   $result = dy_get_server(array('module'=>'trust'
                                  ,'q'=>'get_cash_fee'
                                  ,'method'=>'get'
                                  ,'account'=>$_REQUEST['account']
                                  ,'vip_status'=>$_G['user_result']['vip_status']));

    if ($result['result']=='success'){
        echo $result["fee"];
        exit;
    }
    echo 0;
    exit;
}

?>