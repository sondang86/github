<?php
    $page_security = 'SA_SALESTRANSVIEW';
    $path_to_root = "../..";
    include_once($path_to_root . "/includes/db_pager.inc");
    include_once($path_to_root . "/includes/session.inc");
    include_once($path_to_root . "/sales/includes/sales_ui.inc");
    include_once($path_to_root . "/sales/includes/sales_db.inc");
    include_once($path_to_root . "/reporting/includes/reporting.inc");

    class Customer_Inquiry {
        
        public function get_filtertype() {
            if( isset($_POST['filterType']) ){
                $filterType = $_POST['filterType'];
            } elseif ( isset($_GET['filtertype']) ){
                $filterType = $_GET['filtertype'];
            }
            
            return $filterType;
        }
        
        
        public function get_customer_id(){
            if (isset($_GET['customer_id'])){
            $_POST['customer_id'] = $_GET['customer_id'];
            }
            
            return $_POST['customer_id'];
        }
        
        public function display_customer_summary($customer_record)
        {
                $past1 = get_company_pref('past_due_days');
                $past2 = 2 * $past1;
            if ($customer_record["dissallow_invoices"] != 0)
            {
                echo "<center><font color=red size=4><b>" . _("CUSTOMER ACCOUNT IS ON HOLD") . "</font></b></center>";
            }

                $nowdue = "1-" . $past1 . " " . _('Days');
                $pastdue1 = $past1 + 1 . "-" . $past2 . " " . _('Days');
                $pastdue2 = _('Over') . " " . $past2 . " " . _('Days');

            start_table(TABLESTYLE, "width=80%");
            $th = array(_("Currency"), _("Terms"), _("Current"), $nowdue,
                $pastdue1, $pastdue2, _("Total Balance"));
            table_header($th);

                start_row();
            label_cell($customer_record["curr_code"]);
            label_cell($customer_record["terms"]);
                amount_cell($customer_record["Balance"] - $customer_record["Due"]);
                amount_cell($customer_record["Due"] - $customer_record["Overdue1"]);
                amount_cell($customer_record["Overdue1"] - $customer_record["Overdue2"]);
                amount_cell($customer_record["Overdue2"]);
                amount_cell($customer_record["Balance"]);
                end_row();

                end_table();
        }
        
        public function get_sql_for_customer_inquiry()
        {
            $date_after = date2sql($_POST['TransAfterDate']);
            $date_to = date2sql($_POST['TransToDate']);

                $sql = "SELECT
                        trans.type,
                        trans.trans_no,
                        trans.order_,
                        trans.reference,
                        trans.tran_date,
                        trans.due_date,
                        debtor.name,
                        branch.br_name,
                        debtor.curr_code,
                        (trans.ov_amount + trans.ov_gst + trans.ov_freight
                                + trans.ov_freight_tax + trans.ov_discount)	AS TotalAmount, ";
                if ($_POST['filterType'] != ALL_TEXT)
                        $sql .= "@bal := @bal+(trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount), ";

        //	else
        //		$sql .= "IF(trans.type=".ST_CUSTDELIVERY.",'', IF(trans.type=".ST_SALESINVOICE." OR trans.type=".ST_BANKPAYMENT.",@bal := @bal+
        //			(trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount), @bal := @bal-
        //			(trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount))) , ";
                        $sql .= "trans.alloc AS Allocated,
                        ((trans.type = ".ST_SALESINVOICE.")
                                AND trans.due_date < '" . date2sql(Today()) . "') AS OverDue ,
                        Sum(line.quantity-line.qty_done) AS Outstanding
                        FROM "
                                .TB_PREF."debtor_trans as trans
                                LEFT JOIN ".TB_PREF."debtor_trans_details as line
                                        ON trans.trans_no=line.debtor_trans_no AND trans.type=line.debtor_trans_type,"
                                .TB_PREF."debtors_master as debtor, "
                                .TB_PREF."cust_branch as branch
                        WHERE debtor.debtor_no = trans.debtor_no
                                AND trans.tran_date >= '$date_after'
                                AND trans.tran_date <= '$date_to'
                                AND trans.branch_code = branch.branch_code";

                if ($_POST['customer_id'] != ALL_TEXT)
                        $sql .= " AND trans.debtor_no = ".db_escape($_POST['customer_id']);

                if ($_POST['filterType'] != ALL_TEXT)
                {
                        if ($_POST['filterType'] == '1')
                        {
                                $sql .= " AND (trans.type = ".ST_SALESINVOICE.") ";
                        }
                        elseif ($_POST['filterType'] == '2')
                        {
                                $sql .= " AND (trans.type = ".ST_SALESINVOICE.") ";
                        }
                        elseif ($_POST['filterType'] == '3')
                        {
                                $sql .= " AND (trans.type = " . ST_CUSTPAYMENT
                                                ." OR trans.type = ".ST_BANKDEPOSIT." OR trans.type = ".ST_BANKPAYMENT.") ";
                        }
                        elseif ($_POST['filterType'] == '4')
                        {
                                $sql .= " AND trans.type = ".ST_CUSTCREDIT." ";
                        }
                        elseif ($_POST['filterType'] == '5')
                        {
                                $sql .= " AND trans.type = ".ST_CUSTDELIVERY." ";
                        }

                if ($_POST['filterType'] == '2')
                {
                        $today =  date2sql(Today());
                        $sql .= " AND trans.due_date < '$today'
                                        AND (trans.ov_amount + trans.ov_gst + trans.ov_freight_tax +
                                        trans.ov_freight + trans.ov_discount - trans.alloc > 0) ";
                }
                }
                $sql .= " GROUP BY trans.trans_no, trans.type";

                return $sql;
        }
    }
    
    
    
    function prt_link($row){
               
                $_SESSION['sontest_row'] = $row;
                if ($row['type'] == ST_CUSTPAYMENT || $row['type'] == ST_BANKDEPOSIT) 
                        return print_document_link($row['trans_no']."-".$row['type'], _("Print Receipt"), true, ST_CUSTPAYMENT, ICON_PRINT);
                elseif ($row['type'] == ST_BANKPAYMENT) // bank payment printout not defined yet.
                        return '';
                else	
                        return print_document_link($row['trans_no']."-".$row['type'], _("Print"), true, $row['type'], ICON_PRINT);
        }
        
        
    function edit_link($row)
    {
	if (@$_GET['popup'] || get_voided_entry($row['type'], $row["trans_no"]) || is_closed_trans($row['type'], $row["trans_no"]))
		return '';

	$str = '';
	switch($row['type']) {
		case ST_SALESINVOICE:
			$str = "/sales/customer_invoice.php?ModifyInvoice=".$row['trans_no'];
		break;
		case ST_CUSTCREDIT:
			if ($row['order_']==0) // free-hand credit note
			    $str = "/sales/credit_note_entry.php?ModifyCredit=".$row['trans_no'];
			else	// credit invoice
			    $str = "/sales/customer_credit_invoice.php?ModifyCredit=".$row['trans_no'];
		break;
		case ST_CUSTDELIVERY:
   			$str = "/sales/customer_delivery.php?ModifyDelivery=".$row['trans_no'];
		break;
		case ST_CUSTPAYMENT:
   			$str = "/sales/customer_payments.php?trans_no=".$row['trans_no'];
		break;
	}

            return $str ? pager_link(_('Edit'), $str, ICON_EDIT) : '';
        }
        
        
        function systype_name($dummy, $type)
        {
                global $systypes_array;

                return $systypes_array[$type];
        }
        
        
        function order_view($row)
        {
                return $row['order_']>0 ?
                        get_customer_trans_view_str(ST_SALESORDER, $row['order_'])
                        : "";
        }

        function trans_view($trans)
        {
                return get_trans_view_str($trans["type"], $trans["trans_no"]);
        }

        function due_date($row)
        {
                return	$row["type"] == ST_SALESINVOICE	? $row["due_date"] : '';
        }

        function gl_view($row)
        {
                return get_gl_view_str($row["type"], $row["trans_no"]);
        }

        function fmt_debit($row)
        {
                $value =
                    $row['type']==ST_CUSTCREDIT || $row['type']==ST_CUSTPAYMENT || $row['type']==ST_BANKDEPOSIT ?
                        -$row["TotalAmount"] : $row["TotalAmount"];
                return $value>=0 ? price_format($value) : '';

        }

        function fmt_credit($row)
        {
                $value =
                    !($row['type']==ST_CUSTCREDIT || $row['type']==ST_CUSTPAYMENT || $row['type']==ST_BANKDEPOSIT) ?
                        -$row["TotalAmount"] : $row["TotalAmount"];
                return $value>0 ? price_format($value) : '';
        }

        function credit_link($row)
        {
                if (@$_GET['popup'])
                        return '';
                return $row['type'] == ST_SALESINVOICE && $row["Outstanding"] > 0 ?
                        pager_link(_("Credit This") ,
                                "/sales/customer_credit_invoice.php?InvoiceNumber=". $row['trans_no'], ICON_CREDIT):'';
        }



        function check_overdue($row)
        {
                return $row['OverDue'] == 1
                        && floatcmp($row["TotalAmount"], $row["Allocated"]) != 0;
        }
        
        
?>

