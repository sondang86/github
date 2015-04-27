<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
require_once('refactoring_customer_inquiry.php'); //Toàn bộ functions của page này sẽ nằm ở đây
$customer_inquiry = new Customer_Inquiry;
$page_security = 'SA_SALESTRANSVIEW';

$filter_type = $customer_inquiry->get_filtertype();

if (!@$_GET['popup']){
	$js = "";
	if ($use_popup_windows)
		$js .= get_js_open_window(900, 500);
	if ($use_date_picker)
		$js .= get_js_date_picker();
	$buttonAddNew = null;
	if($filter_type !=0 ){
		switch ($filter_type){
			case 1: $buttonAddNew= button_add_new('sales/sales_order_entry.php','NewInvoice=0'); break;
			case 5: $buttonAddNew= button_add_new('sales/sales_order_entry.php','NewDelivery=0');break;
			default:break;
		}
	}
	//echo $addNewButton;
	page(_($help_context = "Customer Transactions"), isset($_GET['customer_id']), false, "", $js,false,'',$buttonAddNew);
	
}

$customer_id = $customer_inquiry->get_customer_id();
//bug($customer_id);die;

//------------------------------------------------------------------------------------------------
//If have Customer id section
if (!@$_GET['popup'])
	start_form();

if (!empty($customer_id))
	$customer_id = get_global_customer();

start_table(TABLESTYLE_NOBORDER);
start_row();

if (!@$_GET['popup'])
	customer_list_cells(_("Select a customer: "), 'customer_id', null, true, false, false, !@$_GET['popup']);

date_cells(_("From:"), 'TransAfterDate', '', null, -30);
date_cells(_("To:"), 'TransToDate', '', null, 1);

// if (!isset($_POST['filterType'])) $_POST['filterType'] = 0;


cust_allocations_list_cells(null, 'filterType', $filter_type, true);

submit_cells('RefreshInquiry', _("Search"),'',_('Refresh Inquiry'), 'default');
end_row();
end_table();

set_global_customer($customer_id);

div_start('totals_tbl');
if ($customer_id != "" && $customer_id != ALL_TEXT)
{
	$customer_record = get_customer_details($customer_id, $_POST['TransToDate']);
        $customer_inquiry->display_customer_summary($customer_record);
    echo "<br>";
}
div_end();

if(get_post('RefreshInquiry'))
{
	$Ajax->activate('totals_tbl');
}
//------------------------------------------------------------------------------------------------

$sql = $customer_inquiry->get_sql_for_customer_inquiry();

//------------------------------------------------------------------------------------------------
db_query("set @bal:=0");

$cols = array(
	_("Type") => array('fun'=>'systype_name', 'ord'=>''), //
	_("#") => array('fun'=>'trans_view', 'ord'=>''), //
	_("Order") => array('fun'=>'order_view'), //
	_("Reference"), //
	_("Date") => array('name'=>'tran_date', 'type'=>'date', 'ord'=>'desc'), //
	_("Due Date") => array('type'=>'date', 'fun'=>'due_date'), //
	_("Customer") => array('ord'=>''), //
	_("Branch") => array('ord'=>''), //
	_("Currency") => array('align'=>'center'),//
	_("Debit") => array('align'=>'right', 'fun'=>'fmt_debit'),  //
	_("Credit") => array('align'=>'right','insert'=>true, 'fun'=>'fmt_credit'), //
	_("RB") => array('align'=>'right', 'type'=>'amount'), //
		array('insert'=>true, 'fun'=>'gl_view'), //
		array('insert'=>true, 'fun'=>'credit_link'), //
		array('insert'=>true, 'fun'=>'edit_link'), // 
		array('insert'=>true, 'fun'=>'prt_link') //những function này nằm ở refactoring_customer_inquiry.php
	);


if ($customer_id != ALL_TEXT) {
	$cols[_("Customer")] = 'skip';
	$cols[_("Currency")] = 'skip';
}

if ($_POST['filterType'] == ALL_TEXT){
$cols[_("RB")] = 'skip';

}



//Table hiển thị data
$table =& new_db_pager('trans_tbl', $sql, $cols);
$table->set_marker('check_overdue', _("Marked items are overdue."));

$table->width = "85%";

display_db_pager($table);

if (!@$_GET['popup'])
{
	end_form();
	end_page(@$_GET['popup'], false, false);
}
?>
