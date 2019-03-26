<?php
/******************************
 * $File:gopay.class.php
 * $Description:资金托管总接口
******************************/
if (! defined ( 'OS' ) || OS != 'DYOS') die ( 'System Access Denied' );
require_once(SYSTEM_SERVER_MODULES_PATH."borrow/libs/borrow.class.php");
require_once(SYSTEM_SERVER_MODULES_PATH."approve/libs/approve.class.php");
require_once(SYSTEM_SERVER_MODULES_PATH."phone/libs/phone.class.php");
class trustClass
{

	/*
    * Descript：开户
    * Param：不同接口使用不同的参数，具体看《P2P系统开发文档》
    * Return：array
    */
	public function Reg($data =array()){
		global $mysql,$_SYSTEM;
        $data['payment'] = $_SYSTEM['p2p_trust']['type'];
        $_check = self::CheckPayment($data,'reg');
        if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");
        $_class = $data['payment']."Class";
        if ($data['user_id']!=""){
            //获取用户名
            if ($data['username']==""){
                //判断平台的用户是否已经存在
                $sql = "select username from `{users}` where user_id='{$data['user_id']}'";
                $result = $mysql->db_fetch_array($sql);
                if ($result=="") {
                    return "trust_user_error";
                }
                $data['username'] = $result['username'];
            }
            
            if(!isset($data['user_type'])){
                $sql = "select user_type from `{users_info}` where user_id='{$data['user_id']}'";
                $result = $mysql->db_fetch_array($sql);
                if ($result=="") {
                    return "trust_user_error";
                }
                $data['user_type'] = $result['user_type'];
            }

            // if ($data['email']==""){
            //     //判断邮箱是否存在
            //     $sql = "select email from `{email}` where user_id='{$data['user_id']}'";
            //     $result = $mysql->db_fetch_array($sql);
            //     if ($result=="") {
            //         return "trust_email_error";
            //     }
            //     $data['email'] = $result['email'];//手机
            // }

            if ($data['phone']==""){

                //判断邮箱是否存在
                $sql = "select phone from `{phone}` where user_id='{$data['user_id']}'";
                $result = $mysql->db_fetch_array($sql);
                if ($result=="") {
                    return "trust_phone_error";
                }
                $data['phone'] = $result['phone'];//手机
            }
             //汇付托管不用真实姓名和身份证号
            if($data['payment']=='chinapnr'){
                $data['realname'] = '';//真实姓名
                $data['card_id'] = '';
            }else{
                if ($data['realname']=="" || $data['card_id']){
                    //获取实名信息和身份认证
                    $sql = "select realname,card_id,company_name,license_id from `{approve_realname}` where user_id='{$data['user_id']}'";
                    $result = $mysql->db_fetch_array($sql);
                    if ($result=="") {
                        return "trust_approve_error";
                    }
                    
                    if ($result['realname']=="") {
                        return "trust_approve_realname_error";
                    }elseif ($result['card_id']=="") {
                        return "trust_approve_cardid_error";
                    }
                        
                    if($data['user_type']==2){
                        if($result['company_name']==""){
                            return "trust_approve_realname_error1";
                        }else if($result['license_id']==""){
                            return "trust_approve_cardid_error1";
                        }
                    }
                    
                    $data['realname'] = $result['realname'];//真实姓名
                    $data['card_id'] = $result['card_id'];//身份证号码
                    if($data['user_type']==2){
                        $data['company_name'] = $result[company_name];//真实姓名
                        $data['license_id'] = $result['license_id'];//身份证号码
                    }
                }
            }
            //判断平台的用户是否已经通过认证
            $sql = "select user_id from `{trust_users}` where payment='{$data['payment']}' and user_id='{$data['user_id']}'";
            $result = $mysql->db_fetch_array($sql);
            if ($result!="") {
                return "trust_user_exist";
            }
        }else{
            return "trust_user_error";
        }
        $result = $_class::reg($data);

        if (is_array($result)){
            $_data = array();
            $_data['parameter'] = $result['parms'];
            $_data['url'] = $result['url'];
            $_data['auto'] = $data['auto'];//是否自动发送
            $_data['target'] = $data['target'];
            $_result = self::_BuildForm($_data);//结果信息

            //返回结果，所有的资金托管都需要增加此代码
            $_trust = array();
            $_trust['type'] =  'reg';//返回结果
            $_trust['user_id'] =  $data['user_id'];//返回结果
            $_trust['payment'] =  $data['payment'];//资金托管
            $_trust['nid'] =  isset($result['merBillNo'])?$result['merBillNo']:$result['nid'];//订单号
            $_trust['send_msg'] = $_result;
            trustClass::AddTrust($_trust);//结果信息

            return array("form"=>base64_encode(urlencode($_result)),"parms"=> $result['parms']);
        }else{
            return $result;
        }
	}


    /*
    * Descript：开户接口返回
    * Param：不同接口使用不同的参数，具体看《P2P系统开发文档》
    * Return：array
    */
	public function RegReturn($data =array()){
		global $mysql,$_SYSTEM;
        $data['payment'] = $_SYSTEM['p2p_trust']['type'];
        $_check = self::CheckPayment($data);
        if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");
        $_class = $data['payment']."Class";
        $result = $_class::RegReturn(json_decode($data['return_msg'],true));
        if($result['result'] == 'success'){
            //实名认证通过
            require_once (SYSTEM_SERVER_MODULES_PATH.'approve/libs/approve.class.php');
            $approve = array();
            $approve['user_id'] = $result['user_id'];
            $approve['status'] = 1;
            $approve['verify_remark'] = '托管开户自动审核通过！';
            approveClass::CheckRealname($approve);

            //注册送红包
            require_once(SYSTEM_SERVER_MODULES_PATH.'red_envelope/libs/red_envelope.class.php');
            $red_envelope = array();
            $red_envelope['user_id'] = $result['user_id'];//用户id
            $red_envelope['config_id'] = 1;// p2p_red_envelope_config表中的id
            red_envelopeClass::SendRedEnvelope($red_envelope);
        }
        return $result;
	}

   
	/*
	 * Author:gaoyn
	 * Descript：提现-重改
	 * Return：array
	 */
	public function CashOne($data =array()){
	    global $mysql,$_SYSTEM;
	    $data['payment'] = $_SYSTEM['p2p_trust']['type'];
	    $_check = self::CheckPayment($data);
	    if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");
	    $sql = "select trust_userid from `{trust_users}` where user_id='{$data['user_id']}' and payment='{$data['payment']}' ";
	    $result = $mysql->db_fetch_array($sql);
	    if ($result==false) {
	        return array("result"=>"false","code"=>"trust_user_error","remark"=>"提现账号不存在");
	    }
	    $sql1 = "select user_type from `{users_info}` where user_id='{$data['user_id']}' ";
	    $result1 = $mysql->db_fetch_array($sql1);
	    if(empty($result1['user_type'])){
	        return array("result"=>"false","code"=>"trust_user_error","remark"=>"用户类型错误");
	    }
	    $data['user_type'] =  $result1['user_type'];
	    
	    if(floatval($data['money']-$_SYSTEM['p2p_trust'][$_SYSTEM['p2p_trust']['type']]['cash_fee'])<=0) return array("result"=>"false","code"=>$_check,"remark"=>"提现金额不能小于充值手续费");
	    $data['trust_userid'] = $result['trust_userid'];
	    
	    //短信验证码
	    $sql = "select code,phone,addtime from `{phone_smslog}` where user_id={$data['user_id']} and type='cash_new' order by id desc";
	    $result = $mysql->db_fetch_array($sql);
	    if ($result==false || $result['code']!=$data['ajax_phone_code'] || $result['addtime']+5*60<time()){
	        return array("result"=>"false","code"=>"phone_sms_code_error","remark"=>"短信验证码错误");
	    }
	    
	    /*
	     $sql = "select * from `{account_users_bank}` where user_id='{$data['user_id']}'";
	     $_result = $mysql->db_fetch_array($sql);
	     if ($_result==false) {
	     return array("result"=>"false","code"=>"trust_user_bank_error","remark"=>"银行卡号不存在");
	     }
	     $data['bank_result'] = $_result;
	     */
	    $_class = $data['payment']."Class";
	    $result = $_class::Cash($data);//ft_debug($result);
	    if (is_array($result) ){
	        $_data = array();
	        $_data['parameter'] = $result['parms'];
	        $_data['url'] = $result['url'];
	        $_data['auto'] = $data['auto'];//是否自动发送
	        $_data['target'] = $data['target'];//是否新页面打开
	        $_result = self::_BuildForm($_data);//结果信息
	        //ft_debug($_result);
	        
	        //返回结果，所有的资金托管都需要增加此代码
	        $_trust = array();
	        $_trust['type'] =  'cash';//返回结果
	        $_trust['user_id'] =  $data['user_id'];//返回结果
	        $_trust['payment'] =  $data['payment'];//资金托管
	        $_trust['nid'] = $result['nid'];//订单号
	        $_trust['send_msg'] = $_result;
	        self::AddTrust($_trust);//结果信息
	        
	        //添加提现的信息
	        $_data = array();
	        $_data['user_id'] = $data['user_id'];
	        if($_SYSTEM['p2p_trust']['type']=='mmm'){
	            $fee = 0;
	            $fee = $data['money']*$_SYSTEM['p2p_trust'][$_SYSTEM['p2p_trust']['type']]['cash_fee'];
	            $fee = ($fee<1)?1:$fee;
	        }else{
	            $fee = $_SYSTEM['p2p_trust'][$_SYSTEM['p2p_trust']['type']]['cash_fee'];
	        }
	        //xzy 2014-10-23 修复提现金额和双乾坤不一致
	        //$_data['total'] = $data['money'];
	        //$_data['total'] = $data['money']+$fee;
	        $_data['total'] = $data['money'];
	        $_data['account'] = $data['bank_result']['account'];
	        $_data['branch'] = base64_encode($data['bank_result']['branch']);
	        $_data['bank_id'] = $data['bank_result']['bank'];
	        $_data['province'] = $data['bank_result']['province'];
	        $_data['city'] = $data['bank_result']['city'];
	        $_data['name'] = base64_encode($data['bank_result']['name']);
	        $_data['nid'] = $result['nid'];
	        $_data['remark'] = json_encode($_data);
	        $_data['account'] = $data['money'];
	        self::AddCash($_data);
	        return array("form"=>base64_encode(urlencode($_result)),"parms"=> $result['parms'],"result"=>$result);
	    }else{
	        return $result;
	    }
	}
	
   

	/*
	 * Author:gaoyn 2017/12/11
	 * Descript：提现问题
	 * Param：环迅提现成功，平台提现失败
	 * Return：array
	 */
    public function CashReturn1($data =array()){
        global $mysql,$_SYSTEM;
        $data['payment'] = $_SYSTEM['p2p_trust']['type'];
        $_check = self::CheckPayment($data);
        if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");

        $_class = $data['payment']."Class";
        $result = $_class::CashReturn1(json_decode($data['return_msg'],true));//ft_debug($result);
        if (is_array($result) && $result['result']=="success"){
            $sql = "select remark from `{trust_cash}` where nid='{$result["nid"]}'";
            $_result = $mysql->db_fetch_array($sql);
            if ($_result!=false){
                $_data = json_decode($_result['remark'],true);
            }
            if($_SYSTEM['p2p_trust']['type']=='mmm'){
                $_data['total'] = $result['account'];
            }
            if($_SYSTEM['_cash_fee_type']==0){
               $_data['fee'] = $result['fee'];
            }else{
                $_data['fee']= 0.00;
            }
            $_data['credited'] = $result['account']-$_data['fee'];
            $_data['branch'] = base64_decode($_data['branch']);
            $_data['bank'] = base64_decode($_data['name']);
            accountClass::AddCash($_data);//加入提现
            //审核提现记录 异步对账更新  张永洪   20141218
            if(($result['RespCode']=='000000' && $_SYSTEM['p2p_trust']['type'] == "ips") || ($result['RespCode']==000 && $_SYSTEM['p2p_trust']['type'] == "chinapnr") || $_SYSTEM['p2p_trust']['type'] == "mmm"){
                $_cash = array();
                $_cash["status"]=1;
                $_cash["nid"]=$result["nid"];
                $_cash["verify_remark"]='提现成功。';
                $_cash["verify_time"]=time();
                $_result = accountClass::VerifyCash($_cash);
            }

            $sql = "update `{trust_cash}` set status=1,fee='{$_data['fee']}' where nid='{$result["nid"]}'";
            $mysql->db_query($sql);
            return $result;

        }else{
            //异步对账更新  张永洪   20141218
            if($result['ResultCode']==89 || $result['RespCode']==400){
                //避免重复返回
                $sql="select * from `{trust_cash}` where nid='{$result["nid"]}'";
                $_result = $mysql->db_fetch_array($sql);
                if($_result['status']==2){
                    return array("result"=>"error","code"=>"SUCCESS");
                }


                $sql = "update `{trust_cash}` set status=2 where nid='{$result["nid"]}'";
                $mysql->db_query($sql);
                $_cash = array();
                $_cash["status"]=2;
                $_cash["nid"]=$result["nid"];
                $_cash["verify_remark"]='提现被退回';
                $_cash["verify_time"]=time();
                $_result = accountClass::VerifyCash_failed($_cash);
                return array("result"=>"success","code"=>"SUCCESS");
            }else{
                $sql = "update `{trust_cash}` set status=2 where nid='{$result["nid"]}'";
                $mysql->db_query($sql);
                return $result;
            }

        }
    }
    
    
    /*
     * Author:gaoyn 2017/12/11
     * Descript：提现问题
     * Param：环迅提现失败，平台提现成功
     * Return：array
     */
    public function CashReturn2($data =array()){
        global $mysql,$_SYSTEM;
        $data['payment'] = $_SYSTEM['p2p_trust']['type'];
        $_check = self::CheckPayment($data);
        if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");
        
        $_class = $data['payment']."Class";
        $result = $_class::CashReturn2(json_decode($data['return_msg'],true));
        ft_debug($result);
        if (is_array($result) && $result['result']=="success"){
            $sql = "select remark from `{trust_cash}` where nid='{$result["nid"]}'";
            $_result = $mysql->db_fetch_array($sql);
            if ($_result!=false){
                $_data = json_decode($_result['remark'],true);
            }
            if($_SYSTEM['p2p_trust']['type']=='ips'){
                $_data['total'] = $result['account'];
            }
            if($_SYSTEM['_cash_fee_type']==0){
                $_data['fee'] = $result['fee'];
            }else{
                $_data['fee']= 0.00;
            }
            $_data['credited'] = $result['account']-$_data['fee'];
            $_data['branch'] = base64_decode($_data['branch']);
            $_data['bank'] = base64_decode($_data['name']);
            accountClass::DelCash($_data);//加入提现
            //审核提现记录 异步对账更新  张永洪   20141218
            if(($result['RespCode']=='000000' && $_SYSTEM['p2p_trust']['type'] == "ips") || ($result['RespCode']==000 && $_SYSTEM['p2p_trust']['type'] == "chinapnr") || $_SYSTEM['p2p_trust']['type'] == "mmm"){
                /*$_cash = array();
                $_cash["status"]=1;
                $_cash["nid"]=$result["nid"];
                $_cash["verify_remark"]='提现成功。';
                $_cash["verify_time"]=time();
                $_result = accountClass::VerifyCash($_cash);*/
            }
            
            $sql = "update `{trust_cash}` set status=1,fee='{$_data['fee']}' where nid='{$result["nid"]}'";
            $mysql->db_query($sql);
            return $result;
            
        }else{
            //异步对账更新  张永洪   20141218
            if($result['ResultCode']==89 || $result['RespCode']==400){
                //避免重复返回
                $sql="select * from `{trust_cash}` where nid='{$result["nid"]}'";
                $_result = $mysql->db_fetch_array($sql);
                if($_result['status']==2){
                    return array("result"=>"error","code"=>"SUCCESS");
                }
                
                
                $sql = "update `{trust_cash}` set status=2 where nid='{$result["nid"]}'";
                $mysql->db_query($sql);
                $_cash = array();
                $_cash["status"]=2;
                $_cash["nid"]=$result["nid"];
                $_cash["verify_remark"]='提现被退回';
                $_cash["verify_time"]=time();
                $_result = accountClass::VerifyCash_failed($_cash);
                return array("result"=>"success","code"=>"SUCCESS");
            }else{
                $sql = "update `{trust_cash}` set status=2 where nid='{$result["nid"]}'";
                $mysql->db_query($sql);
                return $result;
            }
            
        }
    }
   
    /*
     * 解冻一个商户转账冻结的红包
     */
    public function TransferToUnfreezeEn($data){
        global $mysql,$_SYSTEM;
        $data['payment'] = $_SYSTEM['p2p_trust']['type'];
        $_check = self::CheckPayment($data);
        if ($_check!='success') return array("result"=>"false","code"=>$_check,"remark"=>"托管账号不存在");

        //获取红包信息
        $sql = "SELECT user_id,return_msg,red_status,type FROM `{trust}` WHERE nid='{$data['nid']}'";
        $_result = $mysql->db_fetch_array($sql);
        if ($_result=="" || $_result['type'] != 'TransferToUser' || $_result['red_status'] == 0){
            return array("result"=>"false","code"=>$_check,"remark"=>"转账红包信息不存在或者已被解冻");
        }

        //ft_debug($_result['return_msg']);

        $data['user_id'] = $_result['user_id'];
        $return_msg = json_decode($_result['return_msg'], true);
        //ft_debug($return_msg);
        $data['response'] = $return_msg['response'];

        //判断平台的用户是否已经通过认证
        $sql = "select trust_userid from `{trust_users}`  where user_id='{$data['user_id']}'";
        $_result = $mysql->db_fetch_array($sql);
        if ($_result==""){
            return "trust_user_error";
        }

        $data['trust_userid'] = $_result['trust_userid'];
        $_class = $data['payment']."Class";
        //ft_debug($data);
        $result = $_class::TransferToUnfreezeEn($data);
        if ($result['result'] == "success"){
                        //添加记录
            //ft_debug($result);
            $_recharge = array();
            $_recharge["money"]=$result['data']['account'];
            $_recharge["user_id"]=$result['data']['user_id'];
            $_recharge["payment"] = $data['payment'];
            $_recharge["order_id"]= $result['data']["order_id"];
            $_recharge["remark"]='投资红包发送成功';
            //$result['remark'];//ft_debug($_recharge);
            accountClass::AddTransferEn($_recharge);

            return $result;
        }else{
            return $result;
        }
    }
    
    
}
?>
