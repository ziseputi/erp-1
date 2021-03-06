<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Receipt extends CI_Controller {

    public function __construct(){
        parent::__construct();
		$this->common_model->checkpurview();
		$this->jxcsys = $this->session->userdata('jxcsys');
    }
	
	public function index() {
	    $action = $this->input->get('action',TRUE);
		switch ($action) {
			case 'initReceipt':
			    $this->common_model->checkpurview(125);
			    $this->load->view('scm/receipt/initReceipt');	
				break;  
			case 'editReceipt':
			    $this->common_model->checkpurview(124);
			    $this->load->view('scm/receipt/initReceipt');	
				break;  	
			case 'initUnhxList':
			    $this->load->view('scm/receipt/initUnhxList');
				break; 
			case 'initReceiptList':
			    $this->common_model->checkpurview(124);
			    $this->load->view('scm/receipt/initReceiptList');
				break;
			default:  
			    $this->common_model->checkpurview(124);
			    $this->receiptList();	
		}
	}
	
	public function receiptList(){
		$v = array();
	    $data['status'] = 200;
		$data['msg']    = 'success'; 
		$page = max(intval($this->input->get_post('page',TRUE)),1);
		$rows = max(intval($this->input->get_post('rows',TRUE)),100);
		$matchCon  = str_enhtml($this->input->get_post('matchCon',TRUE));
		$beginDate  = str_enhtml($this->input->get_post('beginDate',TRUE));
		$endDate  = str_enhtml($this->input->get_post('endDate',TRUE));
		$where  = ' and transType=153001'; 
		$where .= $matchCon  ? ' and (contactName like "%'.$matchCon.'%" or description like "%'.$matchCon.'%" or billNo like "%'.$matchCon.'%")' : ''; 
		$where .= $beginDate ? ' and billDate>="'.$beginDate.'"' : ''; 
		$where .= $endDate ? ' and billDate<="'.$endDate.'"' : ''; 
		$offset = $rows * ($page-1);
		$data['data']['page']      = $page;
		$data['data']['records']   = $this->data_model->get_invoice($where,3);   //总条数
		$data['data']['total']     = ceil($data['data']['records']/$rows);                             //总分页数
		$list = $this->data_model->get_invoice($where.' order by id desc limit '.$offset.','.$rows.'');  
		foreach ($list as $arr=>$row) {
		    $v[$arr]['id']           = intval($row['id']);
		    $v[$arr]['amount']       = (float)$row['rpAmount']; 
			$v[$arr]['adjustRate']   = (float)$row['discount'];   //整单折扣
			$v[$arr]['deAmount']     = (float)$row['rpAmount'];    //本次预收款
			$v[$arr]['billDate']     = $row['billDate'];
			$v[$arr]['bDeAmount']    = (float)$row['hxAmount'];   //本次核销
			$v[$arr]['hxAmount']     = (float)$row['hxAmount'];   //本次核销
			$v[$arr]['contactName']  = $row['contactName'];
			$v[$arr]['description']  = $row['description'];
			$v[$arr]['billNo']       = $row['billNo'];
		}
		$data['data']['rows']        = $v;
		die(json_encode($data));
	}
	
	public function exportReceipt(){
	    $this->common_model->checkpurview(128);
		$name = 'receipt_record_'.date('YmdHis').'.xls';
		sys_csv($name);
		$this->common_model->logs('导出收款单:'.$name);
		$matchCon  = str_enhtml($this->input->get_post('matchCon',TRUE));
		$beginDate = str_enhtml($this->input->get_post('beginDate',TRUE));
		$endDate   = str_enhtml($this->input->get_post('endDate',TRUE));
		$locationId   = intval($this->input->get_post('locationId',TRUE));
		$where = ' and transType=153001';
		$where .= $matchCon  ? ' and (contactName like "%'.$matchCon.'%" or billNo like "%'.$matchCon.'%")' : ''; 
		$where .= $beginDate ? ' and billDate>="'.$beginDate.'"' : ''; 
		$where .= $endDate ? ' and billDate<="'.$endDate.'"' : ''; 
		$data['list1'] = $this->data_model->get_invoice($where.' order by id desc'); 
		$data['list2'] = $this->data_model->get_account_info($where.' order by billDate');  
		$this->load->view('scm/receipt/exportReceipt',$data);  
	}
	 
	
    //新增
	public function add(){
	    $this->common_model->checkpurview(125);
	    $data = $this->input->post('postData',TRUE);
		if (strlen($data)>0) {
		     $data = (array)json_decode($data, true);
			 $this->validform($data);
			 $info['billNo']        = str_no('SKD');
			 $info['billType']      = 'RECEIPT';
			 $info['transType']     = 153001;
			 $info['transTypeName'] = '收款';
			 $info['buId']          = intval($data['buId']);
			 $info['billDate']      = $data['date'];
			 $info['description']   = $data['description'];
			 $info['uid']           = $this->jxcsys['uid'];
			 $info['userName']      = $this->jxcsys['name'];
			 $this->db->trans_begin();
			 $iid = $this->mysql_model->insert(INVOICE,$info);
			 $rpAmount = 0;
			 if (is_array($data['entries']) && count($data['entries'])>0) {
			     foreach ($data['entries'] as $arr=>$row) {
				     $v[$arr]['iid']         = $iid;
					 $v[$arr]['billId']      = $row['billId'];
				     $v[$arr]['billNo']      = $info['billNo'];
					 $v[$arr]['billDate']    = $info['billDate'];
					 $v[$arr]['transType']   = $info['transType'];
					 $v[$arr]['billType']    = $row['billType'];
					 $v[$arr]['billPrice']   = (float)$row['billPrice'];
					 $v[$arr]['hasCheck']    = (float)$row['hasCheck'];
					 $v[$arr]['notCheck']    = (float)$row['notCheck'];
					 $rpAmount         +=    $v[$arr]['nowCheck']    = (float)$row->nowCheck;
				 } 
				 if (isset($v)) { 
					 $this->mysql_model->insert(RECEIPT_INFO,$v);  
				 }
			 }
			 $amount = 0;
			 if (isset($data['accounts']) && count($data['accounts'])>0) {
			     foreach ($data['accounts'] as $arr=>$row) {
				     $s[$arr]['iid']           = $iid;
				     $s[$arr]['billNo']        = $info['billNo'];
				     $s[$arr]['buId']          = $info['buId'];
					 $s[$arr]['billType']      = $info['billType'];
				     $s[$arr]['billDate']      = $info['billDate']; 
					 $s[$arr]['transType']     = $info['transType'];
					 $s[$arr]['transTypeName'] = $info['transTypeName'];
					 $s[$arr]['accId']         = $row['accId'];
					 $s[$arr]['payment']       = (float)$row['payment']; 
					 $s[$arr]['wayId']         = $row['wayId'];
					 $s[$arr]['settlement']    = $row['settlement'];
					 $s[$arr]['remark']        = $row['remark'];
					 $amount            +=     (float)$row['payment'];
				} 
				if (isset($s)) {  
					$this->mysql_model->insert(ACCOUNT_INFO,$s);
				}
             }
			 $info['amount']    =  0; 
			 $info['rpAmount']  =  $amount; 
			 $info['arrears']   =   -$amount; 
			 $this->mysql_model->update(INVOICE,$info,'(id='.$iid.')');
			 if ($this->db->trans_status() === FALSE) {
			    $this->db->trans_rollback();
				str_alert(-1,'SQL错误回滚'); 
			 } else {
			    $this->db->trans_commit();
				$this->common_model->logs('新增收款单 单据编号：'.$info['billNo']);
				str_alert(200,'success',array('id'=>$iid)); 
			 }
		}
		str_alert(-1,'提交的是空数据'); 
    } 
	
	
	public function addnew(){
	    $this->add();
    } 
	
	//修改
	public function updateReceipt(){
	    $this->common_model->checkpurview(126);
	    $data = $this->input->post('postData',TRUE);
		if (strlen($data)>0) {
		     $data = (array)json_decode($data, true);
			 $this->validform($data);
			 $id   = intval($data['id']);	  
			 $invoice = $this->mysql_model->get_row(INVOICE,'(id='.$id.') and transType=153001');
			 count($invoice)<1 && str_alert(-1,'单据不存在');
			 $info['buId']          = intval($data['buId']);
			 $info['billType']      = 'RECEIPT';
			 $info['billDate']      = $data['date'];
			 $info['transType']     = 153001;
			 $info['transTypeName'] = '收款';
			 $info['description']   = $data['description'];
			 $info['uid']           = $this->jxcsys['uid'];
			 $info['userName']      = $this->jxcsys['name'];
			 $info['modifytime']    = date('Y-m-d H:i:s');
			 $this->db->trans_begin();
			 $this->mysql_model->delete(RECEIPT_INFO,'(iid='.$id.')');
			 $this->mysql_model->delete(ACCOUNT_INFO,'(iid='.$id.')');
			 $rpAmount = 0;
			 if (isset($data['entries']) && count($data['entries'])>0) {
			     foreach ($data['entries'] as $arr=>$row) {
				     $v[$arr]['iid']         = $id;
					 $v[$arr]['billId']      = $row['billId'];
				     $v[$arr]['billNo']      = $row['billNo'];
					 $v[$arr]['billDate']    = $row['billDate'];
					 $v[$arr]['transType']   = $row['transType'];
					 $v[$arr]['billType']    = $row['billType'];
					 $v[$arr]['billPrice']   = (float)$row['billPrice'];
					 $v[$arr]['hasCheck']    = (float)$row['hasCheck'];
					 $v[$arr]['notCheck']    = (float)$row['notCheck'];
					 $rpAmount         +=    $v[$arr]['nowCheck']    = (float)$row->nowCheck;
				 } 
				 if (isset($v)) { 
					 $this->mysql_model->insert(RECEIPT_INFO,$v);  
				 }
			 }
			 $amount = 0;
			 if (isset($data['accounts']) && count($data['accounts'])>0) {
			     foreach ($data['accounts'] as $arr=>$row) {
				     $s[$arr]['iid']           = $id;
				     $s[$arr]['billNo']        = $invoice['billNo'];
				     $s[$arr]['buId']          = $info['buId'];
					 $s[$arr]['billType']      = $info['billType'];
					 $s[$arr]['transType']     = $info['transType'];
					 $s[$arr]['transTypeName'] = $info['transTypeName'];
				     $s[$arr]['billDate']      = $info['billDate']; 
					 $s[$arr]['accId']         = $row['accId'];
					 $s[$arr]['payment']       = (float)$row['payment']; 
					 $s[$arr]['wayId']         = $row['wayId'];
					 $s[$arr]['settlement']    = $row['settlement'];
					 $s[$arr]['remark']        = $row['remark'];
					 $amount            +=     (float)$row['payment']; 
				}  
				if (isset($s)) { 
					$this->mysql_model->insert(ACCOUNT_INFO,$s);
				}
             }
			 $info['amount']    =  0; 
			 $info['rpAmount']  =  $amount; 
			 $info['arrears']   =   -$amount; 
			 $this->mysql_model->update(INVOICE,$info,'(id='.$id.')');
			 if ($this->db->trans_status() === FALSE) {
			    $this->db->trans_rollback();
				str_alert(-1,'SQL错误回滚'); 
			 } else {
			    $this->db->trans_commit();
				$this->common_model->logs('修改收款单 单据编号：'.$invoice['billNo']);
				str_alert(200,'success',array('id'=>$id)); 
			 }
		} else {
		    str_alert(-1,'参数错误'); 
		}
    }	
    
	//信息 
    public function update() {
	    $this->common_model->checkpurview(124);
	    $id   = intval($this->input->get_post('id',TRUE));
		$data = $this->data_model->get_invoice('and (a.id='.$id.') and transType=153001',1); 
		if (count($data)>0) {
		    $s = array(); 
			$info['status'] = 200;
			$info['msg']    = 'success'; 
			$info['data']['id']              = intval($data['id']);
			$info['data']['buId']            = intval($data['buId']);
			$info['data']['contactName']     = $data['contactName'];
			$info['data']['date']            = $data['billDate'];
			$info['data']['billNo']          = $data['billNo'];
			$info['data']['discount']        = (float)$data['discount'];
			$info['data']['payment']         = (float)$data['payment'];
			$info['data']['status']          = 'edit';
			$info['data']['userName']        = $data['userName'];
			$accounts = $this->data_model->get_account_info('and (iid='.$id.') order by id');  
			foreach ($accounts as $arr=>$row) {
			    $s[$arr]['accId']         = intval($row['accId']);
				$s[$arr]['accName']       = $row['accountName']; 
				$s[$arr]['payment']       = (float)$row['payment'];   
				$s[$arr]['wayId']         = (float)$row['wayId']; 
				$s[$arr]['remark']        = $row['remark'];
				$s[$arr]['wayName']       = $row['categoryName']; 
				$s[$arr]['settlement']    = $row['settlement']; 
		    }  
			$info['data']['accounts']     = $s;
			$v = array(); 
			$list = $this->mysql_model->get_results(RECEIPT_INFO,'(iid='.$id.') order by id desc');  
			foreach ($list as $arr=>$row) {
			    $v[$arr]['billId']      = intval($row['billId']);
			    $v[$arr]['billNo']      = $row['billNo'];
				$v[$arr]['billDate']    = $row['billDate'];
				$v[$arr]['transType']   = $row['transType'];
				$v[$arr]['billType']    = $row['billType'];
				$v[$arr]['billPrice']   = (float)$row['billPrice'];
				$v[$arr]['hasCheck']    = (float)$row['hasCheck'];
				$v[$arr]['notCheck']    = (float)$row['notCheck'];
				$v[$arr]['nowCheck']    = (float)$row['nowCheck'];
				$v[$arr]['type']        = 1;
			}
			$info['data']['entries']    = $v;
			die(json_encode($info));
		} else { 
		    str_alert(-1,'参数错误'); 
		}
    }
	
	//删除
    public function delete() {
	    $this->common_model->checkpurview(127);
	    $id   = intval($this->input->get('id',TRUE));
		$data = $this->mysql_model->get_row(INVOICE,'(id='.$id.') and transType=153001');  
		if (count($data)>0) {
		    $this->db->trans_begin();
			$info['isDelete'] = 1;
		    $this->db->trans_begin();
			$this->mysql_model->update(INVOICE,$info,'(id='.$id.')');   
			//$this->mysql_model->update(RECEIPT_INFO,$info,'(iid='.$id.')');   
			$this->mysql_model->update(ACCOUNT_INFO,$info,'(iid='.$id.')');    
			if ($this->db->trans_status() === FALSE) {
			    $this->db->trans_rollback();
				str_alert(-1,'删除失败'); 
			} else {
			    $this->db->trans_commit();
				$this->common_model->logs('删除收款单 单据编号：'.$data['billNo']);
				str_alert(200,'success'); 	 
			}
		}
		str_alert(-1,'单据不存在,或已被删除'); 
	}
	
	//公共验证
	private function validform($data) {
	    if (isset($data['entries'])&&is_array($data['entries'])) {
		
		} else {	
		    str_alert(-1,'提交的是空数据');   
        }
		$this->mysql_model->get_count(CONTACT,'(id='.intval($data['buId']).')')<1 && str_alert(-1,'请选择客户，客户不能为空！'); 
	}   
	

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */