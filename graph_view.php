<?php
// TODO:
// 1. Wrap PDF output around it.		-
// 2. Look for leafs, and call exta grow_right_pane_tree for each leaf

// Most of this is from Cacti - Copyright kept for postarity
// Modifications to premit the generation of PDFs is done by OSTLabs Inc.
// http://www.ostlabs.com


//phpinfo(); die();

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// $guest_account = true;
include("../include/global.php");
include($config["include_path"] . "/global_arrays.php");
include_once($config["library_path"] . "/data_query.php");
include_once($config["library_path"] . "/tree.php");
include_once($config["library_path"] . "/html_utility.php");

$out_pdf=true;

/* ================= input validation ================= */
input_validate_input_number(get_request_var("branch_id"));
input_validate_input_number(get_request_var("hide"));
input_validate_input_number(get_request_var("tree_id"));
input_validate_input_number(get_request_var("leaf_id"));
input_validate_input_number(get_request_var("rra_id"));
input_validate_input_regex(get_request_var_request('graph_list'), "^([\,0-9]+)$");
input_validate_input_regex(get_request_var_request('graph_add'), "^([\,0-9]+)$");
input_validate_input_regex(get_request_var_request('graph_remove'), "^([\,0-9]+)$");
/* ==================================================== */

// Time Stuff
//input_validate_input_number(get_request_var("start_time"));
//input_validate_input_number(get_request_var("end_time"));


//	$access_denied = false;
//	$tree_parameters = array();

	if ((!isset($_GET["tree_id"])) && (isset($_SESSION['dhtml_tree']))) {
//		unset($_SESSION["dhtml_tree"]);
	}

	if (isset($_GET["tree_id"])) {
//		$_SESSION["sess_graph_view_last_tree"] = get_browser_query_string();
	}


if (empty($_GET["start_time"])) {
    $start_time = strtotime(date('m/d/Y') . " -1 week")+86400; // Start time is default 1 week ago
} elseif (($start_time = strtotime($_GET["start_time"])) === false) {
    echo "The string (" . $_GET["start_time"] .") is bogus"; exit;
}

if (empty($_GET["end_time"])) {
    $end_time = strtotime(date('m/d/Y'))+86400; // Start time is default 1 week ago
} elseif (($end_time = strtotime($_GET["end_time"])) === false) {
    echo "The string (" . $_GET["end_time"] .") is bogus"; exit;
}

$title = get_title((isset($_GET["tree_id"]) ? $_GET["tree_id"] : 0), (isset($_GET["leaf_id"]) ? $_GET["leaf_id"] : 0));
$graph_list = get_graphlist((isset($_GET["tree_id"]) ? $_GET["tree_id"] : 0), (isset($_GET["leaf_id"]) ? $_GET["leaf_id"] : 0), (isset($_GET["host_group_data"]) ? urldecode($_GET["host_group_data"]) : 0));

if ($out_pdf) {
    require_once('tcpdf/config/lang/eng.php');
    require_once('tcpdf/tcpdf.php');
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('');
    $pdf->SetTitle('Capacity Report');
    $pdf->SetSubject($title);
    $pdf->SetKeywords('');
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $title, "");
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->setLanguageArray($l);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->AddPage();

    ob_start();
}
/* start graph display */

if (!$out_pdf) print "$title";

html_graph_area_pdf($graph_list, "", "view_type=tree&graph_start=$start_time&graph_end=$end_time");

if ($out_pdf) {
    $data = ob_get_contents();
    ob_end_clean();
    $pdf->writeHTML($data, true, false, true, false, '');
    $pdf->lastPage();
    $pdf->Output('report.pdf', 'I');
}

function get_title($tree_id, $leaf_id) {
	$title           = "";
	$title_delimeter = "";

	$leaf      = db_fetch_row("SELECT order_key, title, host_id, host_grouping_type
					FROM graph_tree_items
					WHERE id=$leaf_id");

	if (!empty($tree_id)) { $tree_name = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id"); }
	if (!empty($leaf_id)) { $leaf_name = $leaf["title"]; }
	if (!empty($leaf_id)) { $host_name = db_fetch_cell("SELECT host.description FROM (graph_tree_items,host) WHERE graph_tree_items.host_id=host.id AND graph_tree_items.id=$leaf_id"); }

	if (!empty($tree_name)) { $title .= $title_delimeter . "Tree:" . htmlspecialchars($tree_name); $title_delimeter = "-> "; }
	if (!empty($leaf_name)) { $title .= $title_delimeter . "Leaf:" . htmlspecialchars($leaf_name); $title_delimeter = "-> "; }
	if (!empty($host_name)) { $title .= $title_delimeter . "Host:" . htmlspecialchars($host_name); $title_delimeter = "-> "; }
	if (!empty($host_group_data_name)) { $title .= $title_delimeter . " $host_group_data_name"; $title_delimeter = "-> "; }

	return $title;
}

function get_graphlist($tree_id, $leaf_id, $host_group_data) {
	global $current_user, $colors, $config, $graphs_per_page, $graph_timeshifts;

	$leaf_type = get_tree_item_type($leaf_id);

	$sql_where       = "";
	$sql_join        = "";
	$title           = "";
	$title_delimeter = "";
	$search_key      = "";

	$leaf      = db_fetch_row("SELECT order_key, title, host_id, host_grouping_type
					FROM graph_tree_items
					WHERE id=$leaf_id");


	if (empty($tree_id)) { return; }

	if (isset($_REQUEST["tree_id"])) {
		$nodeid = "tree_" . get_request_var_request("tree_id");
	}

	if (isset($_REQUEST["leaf_id"])) {
		$nodeid .= "_leaf_" . get_request_var_request("leaf_id");
	}

	if (isset($_REQUEST["host_group_data"])) {
		$type_id = explode(":", get_request_var_request("host_group_data"));

		if ($type_id[0] == "graph_template") {
			$nodeid .= "_hgd_gt_" . $type_id[1];
		}elseif ($type_id[0] == "data_query") {
			$nodeid .= "_hgd_dq_" . $type_id[1];
		}else{
			$nodeid .= "_hgd_dqi" . $type_id[1] . "_" . $type_id[2];
		}
	}

	/// clean up search string 
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_post("filter"));
	}


	$graph_list = array();

	if (($leaf_type == "header") || (empty($leaf_id))) {
		if (strlen(get_request_var_request("filter"))) {
			$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%' OR graph_templates_graph.title LIKE '%" . get_request_var_request("filter") . "%')");
		}

		$graph_list = db_fetch_assoc("SELECT
			graph_tree_items.id,
			graph_tree_items.title,
			graph_tree_items.local_graph_id,
			graph_tree_items.rra_id,
			graph_tree_items.order_key,
			graph_templates_graph.height,
			graph_templates_graph.title_cache as title_cache
			FROM (graph_tree_items,graph_local)
			LEFT JOIN graph_templates_graph ON (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id AND graph_tree_items.local_graph_id>0)
			$sql_join
			WHERE graph_tree_items.graph_tree_id=$tree_id
			AND graph_local.id=graph_templates_graph.local_graph_id
			AND graph_tree_items.order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'
			AND graph_tree_items.local_graph_id>0
			$sql_where
			GROUP BY graph_tree_items.id
			ORDER BY graph_tree_items.order_key");
	}elseif ($leaf_type == "host") {
		/* graph template grouping */
		if ($leaf["host_grouping_type"] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$graph_templates = db_fetch_assoc("SELECT
				graph_templates.id,
				graph_templates.name
				FROM (graph_local,graph_templates,graph_templates_graph)
				WHERE graph_local.id=graph_templates_graph.local_graph_id
				AND graph_templates_graph.graph_template_id=graph_templates.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (empty($graph_template_id) ? "" : "AND graph_templates.id=$graph_template_id") . "
				GROUP BY graph_templates.id
				ORDER BY graph_templates.name");

			/* for graphs without a template */
			array_push($graph_templates, array(
				"id" => "0",
				"name" => "(No Graph Template)"
				));

			if (sizeof($graph_templates) > 0) {
			foreach ($graph_templates as $graph_template) {
				if (strlen(get_request_var_request("filter"))) {
					$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%')");
				}

				$graphs = db_fetch_assoc("SELECT
					graph_templates_graph.title_cache,
					graph_templates_graph.local_graph_id,
					graph_templates_graph.height
					FROM (graph_local,graph_templates_graph)
					$sql_join
					WHERE graph_local.id=graph_templates_graph.local_graph_id
					AND graph_local.graph_template_id=" . $graph_template["id"] . "
					AND graph_local.host_id=" . $leaf["host_id"] . "
					$sql_where
					ORDER BY graph_templates_graph.title_cache");

				/* let's sort the graphs naturally */
				usort($graphs, 'naturally_sort_graphs');

				if (sizeof($graphs)) {
				foreach ($graphs as $graph) {
					$graph["graph_template_name"] = $graph_template["name"];
					array_push($graph_list, $graph);
				}
				}
			}
			}
		/* data query index grouping */
		}elseif ($leaf["host_grouping_type"] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$data_queries = db_fetch_assoc("SELECT
				snmp_query.id,
				snmp_query.name
				FROM (graph_local,snmp_query)
				WHERE graph_local.snmp_query_id=snmp_query.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (!isset($data_query_id) ? "" : "and snmp_query.id=$data_query_id") . "
				GROUP BY snmp_query.id
				ORDER BY snmp_query.name");

			/* for graphs without a data query */
			if (empty($data_query_id)) {
				array_push($data_queries, array(
					"id" => "0",
					"name" => "Non Query Based"
					));
			}

			if (sizeof($data_queries) > 0) {
			foreach ($data_queries as $data_query) {
				/* fetch a list of field names that are sorted by the preferred sort field */
				$sort_field_data = get_formatted_data_query_indexes($leaf["host_id"], $data_query["id"]);

				if (strlen(get_request_var_request("filter"))) {
					$sql_where = (empty($sql_where) ? "" : "AND (title_cache LIKE '%" . get_request_var_request("filter") . "%')");
				}

				/* grab a list of all graphs for this host/data query combination */
				$graphs = db_fetch_assoc("SELECT
					graph_templates_graph.title_cache,
					graph_templates_graph.local_graph_id,
					graph_templates_graph.height,
					graph_local.snmp_index
					FROM (graph_local, graph_templates_graph)
					$sql_join
					WHERE graph_local.id=graph_templates_graph.local_graph_id
					AND graph_local.snmp_query_id=" . $data_query["id"] . "
					AND graph_local.host_id=" . $leaf["host_id"] . "
					" . (empty($data_query_index) ? "" : "and graph_local.snmp_index='$data_query_index'") . "
					$sql_where
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache");

				/* re-key the results on data query index */
				if (sizeof($graphs) > 0) {
					/* let's sort the graphs naturally */
					usort($graphs, 'naturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph{$graph["snmp_index"]}{$graph["local_graph_id"]} = $graph["title_cache"];
						$graphs_height[$graph["local_graph_id"]] = $graph["height"];
					}
				}

				/* using the sorted data as they key; grab each snmp index from the master list */
				while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
					/* render each graph for the current data query index */
					if (isset($snmp_index_to_graph[$snmp_index])) {
						while (list($local_graph_id, $graph_title) = each($snmp_index_to_graph[$snmp_index])) {
							/* reformat the array so it's compatable with the html_graph* area functions */
							array_push($graph_list, array("data_query_name" => $data_query["name"], "sort_field_value" => $sort_field_value, "local_graph_id" => $local_graph_id, "title_cache" => $graph_title, "height" => $graphs_height[$graph["local_graph_id"]]));
						}
					}
				}
			}
			}
		}
	}

	return $graph_list;

}

function naturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}

function html_graph_area_pdf(&$graph_array, $no_graphs_message = "", $extra_url_args = "") {
	global $config;

	$srv = "http://" . $_SERVER["SERVER_NAME"] . dirname($_SERVER["PHP_SELF"]) . "/";

	$i = 0;
	if (sizeof($graph_array) > 0) {
		foreach ($graph_array as $graph) {
			if (isset($graph["graph_template_name"])) {
				if (isset($prev_graph_template_name)) {
					if ($prev_graph_template_name != $graph["graph_template_name"]) {
						$print  = true;
						$prev_graph_template_name = $graph["graph_template_name"];
					}else{
						$print = false;
					}
				}else{
					$print  = true;
					$prev_graph_template_name = $graph["graph_template_name"];
				}

				if ($print) {
					pdf_print ("Graph Template: " . htmlspecialchars($graph["graph_template_name"]));
				}
			}elseif (isset($graph["data_query_name"])) {
				if (isset($prev_data_query_name)) {
					if ($prev_data_query_name != $graph["data_query_name"]) {
						$print  = true;
						$prev_data_query_name = $graph["data_query_name"];
					}else{
						$print = false;
					}
				}else{
					$print  = true;
					$prev_data_query_name = $graph["data_query_name"];
				}

				if ($print) {
					pdf_print ("Data Query: " . htmlspecialchars($graph["data_query_name"]));
				}
				print $graph["sort_field_value"];
			}

			$img = htmlspecialchars($srv."graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=0" . (($extra_url_args == "") ? "" : "&$extra_url_args"));
			pdf_print ("<img src='$img' width='603' height='279' />", true);

			$i++;
		}
	}else{
		if ($no_graphs_message != "") {
			print "<em>$no_graphs_message</em>";
		}
	}
}

function pdf_print($text, $html=false) {
    global $out_pdf, $pdf;
    if ($out_pdf && !$html) {
        $pdf->SetFont('times', '', 9);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Write(0, $text, '', 0, 'L', true, 0, false, true, 0);
    } elseif ($out_pdf && $html) {
	$pdf->writeHTML($text, true, false, true, false, '');
    } else {
        echo "<BR>$text<BR>";
    }
}