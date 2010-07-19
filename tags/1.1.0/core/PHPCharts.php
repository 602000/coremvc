<?php
/**
 * PHPCharts�����չ
 * 
 * ����CoreMVC�����չ�ļ�����Ҫ��PHPCharts���Ŀ¼��
 * core.php ---> <b>core/PHPCharts.php</b> ---> PHPCharts���Ŀ¼
 */

/**
 * ִ��(execute)
 */

define("CHARTS_SOURCE", dirname(__FILE__) . '/' . basename(__FILE__ , '.php') . '/'); 
define("BAR_CHART", 1);
define("LINE_CHART", 2);
define("PIE_CHART", 3);
define("BAR_LINE_CHART", 4);

include(CHARTS_SOURCE . "function.php");
include(CHARTS_SOURCE . "gridchartclass.php");
include(CHARTS_SOURCE . "colormanagerclass.php");
include(CHARTS_SOURCE . "chartclass.php");
include(CHARTS_SOURCE . "valueclass.php");
include(CHARTS_SOURCE . "axisclass.php");
include(CHARTS_SOURCE . "colornameclass.php");
include(CHARTS_SOURCE . "pieclass.php");
?>