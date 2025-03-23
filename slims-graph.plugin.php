<?php
/**
 * Plugin Name: SLiMS Graph Network Analysis
 * Plugin URI: https://github.com/erwansetyobudi/slims-graph
 * Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
 * Version: 1.0.0
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi/
 */


define('SGNA', __DIR__);

use SLiMS\Plugins;
// get plugin instance
$plugin = \SLiMS\Plugins::getInstance();

// registering menus

$plugin->registerMenu('opac', 'Author Network', SGNA . DS . '/pages/author_network.inc.php');
$plugin->registerMenu('opac', 'Loan Item Network', SGNA . DS . '/pages/loan_item_network.inc.php');
$plugin->registerMenu('opac', 'Publisher Topic Network', SGNA . DS . '/pages/publisher_topic_network.inc.php');
$plugin->registerMenu('opac', 'Topic Chart', SGNA . DS . '/pages/topic_chart.inc.php');
$plugin->registerMenu('opac', 'Gender Visitor Chart', SGNA . DS . '/pages/gender_visitor_chart.inc.php');
$plugin->registerMenu('opac', 'Title Year Trend', SGNA . DS . '/pages/title_year_trend.inc.php');
$plugin->registerMenu('opac', 'Topic Network', SGNA . DS . '/pages/topic_network.inc.php');

