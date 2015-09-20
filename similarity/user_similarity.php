<?php
// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * List most similar submission files of one user in all activities
 *
 * @package mod_vpl
 * @copyright Juan Carlos Rodríguez-del-Pino
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

require_once dirname(__FILE__).'/../../../config.php';
require_once dirname(__FILE__).'/../locallib.php';
require_once dirname(__FILE__).'/../vpl.class.php';
require_once dirname(__FILE__).'/../vpl_submission.class.php';
require_once dirname(__FILE__).'/similarity_factory.class.php';
require_once dirname(__FILE__).'/similarity_base.class.php';
require_once dirname(__FILE__).'/similarity_sources.class.php';
require_once dirname(__FILE__).'/similarity_form.class.php';
require_once dirname(__FILE__).'/clusters.class.php';
require_once dirname(__FILE__).'/../views/status_box.class.php';
ini_set('memory_limit','256M');

require_login();

$id = required_param('id', PARAM_INT);   // course
$userid = required_param('userid', PARAM_INT);
$time_limit = 600; // 10 minutes
//Check course existence
if (! $course = $DB->get_record("course", array('id' => $id))) {
    print_error('invalidcourseid','',$id);
}
require_course_login($course);
$user = $DB->get_record('user',array('id' => $userid));
if(!$user){
    print_error('invaliduserid','',$id);
}

$strtitle=get_string('listsimilarity',VPL);
$PAGE->set_url('/mod/vpl/similarity/user_similarity.php',array('id' => $id,'userid'=>$userid));
$PAGE->navbar->add($strtitle);
$PAGE->requires->css(new moodle_url('/mod/vpl/css/similarity.css'));
$PAGE->set_title(fullname($user).':'.$strtitle);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($user));
echo '<h2>'.$strtitle.'</h2>';
//$vpl->require_capability(VPL_SIMILARITY_CAPABILITY);
//\mod_vpl\event\vpl_similarity_report_viewed::log($vpl);
//Print header

$ovpls = get_all_instances_in_course(VPL,$course);
$timenow = time();
$vpls = array();
//Get and select vpls to show
foreach ($ovpls as $ovpl) {
    $vpl = new mod_vpl(false,$ovpl->id);
    $instance = $vpl->get_instance();
    //Example NO
    if($instance->example){
        continue;
    }
    //Open and limited NO
    if($timenow >= $instance->startdate && $timenow <= $instance->duedate){
        continue;
    }
    $vpls[]=$vpl;
}

@set_time_limit($time_limit);
//Prepare table construction
$firstname = get_string('firstname');
$lastname  = get_string('lastname');
if ($CFG->fullnamedisplay == 'lastname firstname') {
    $name = $lastname.' / '.$firstname;
} else {
    $name = $firstname.' / '.$lastname;
}
$with    = get_string('similarto',VPL);
$table = new html_table();
$table->head  = array ($name, '',$with);
$table->align = array ('Left', 'center', 'left');
$table->size = array ('60','','60');
$table->data = array();

$outputsize=array(1,1,1,2,2,2,2,3,3,3,3,3);
//Process every activity selected

$bars=array();
$relatedusers=array();
foreach ($vpls as $vpl) {
    //debugging("Adding activity files", DEBUG_DEVELOPER);
    $simil =array();
    vpl_similarity_preprocess::user_activity($simil,$vpl,$userid);
    $nuserfiles=count($simil);
    if($nuserfiles>0){
        $activity_load_box = new vpl_progress_bar(s($vpl->get_printable_name()));
        $bars[]=$activity_load_box;
        vpl_similarity_preprocess::activity($simil,$vpl,array(),true,false,$activity_load_box);
        $search_progression = new vpl_progress_bar(get_string('similarity',VPL));
        $bars[]=$search_progression;
        if($nuserfiles>= count($outputsize)){
            $noutput=4;
        }else{
            $noutput=$outputsize[$nuserfiles];
        }
        $selected = vpl_similarity::get_selected($simil,$noutput,$nuserfiles,$search_progression);
        if(count($selected)>0){
            $table->data[] = array ($vpl->get_printable_name(),'','');
            foreach($selected as $case){
                $table->data[] = array (
                        $case->first->show_info(),
                        $case->get_link(),
                        $case->second->show_info());
                $other=$case->second->get_userid();
                if(!isset($relatedusers[$other])){
                    $relatedusers[$other]=1;
                }else{
                    $relatedusers[$other]++;
                }
            }
        }
    }
}

$extinfo=false;
foreach($bars as $bar){
    $bar->hide();
}
if(count($table->data)){
    echo html_writer::table($table);
}else{
    echo $OUTPUT->box(get_string('noresults'));
}
if(count($relatedusers)>0){
    arsort($relatedusers);
    $table = new html_table();
    $table->head  = array ('#',$name);
    $table->align = array ('Left', 'left');
    $table->data = array();
    foreach($relatedusers as $otheruserid => $rel){
        if($rel<2){
            break;
        }
        $otheruser= $DB->get_record('user',array('id' => $otheruserid));
        $table->data[]=array(
        	$rel,
            $vpl->user_fullname_picture($otheruser)
        );
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
