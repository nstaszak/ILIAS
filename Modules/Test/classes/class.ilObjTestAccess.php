<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\Modules\Test\AccessFileUploadAnswer;
use ILIAS\Modules\Test\AccessQuestionImage;
use ILIAS\Modules\Test\AccessFileUploadPreview;
use ILIAS\Modules\Test\SimpleAccess;
use ILIAS\Modules\Test\Readable;
use ILIAS\Data\Result;
use ILIAS\Data\Result\Error;

include_once "./Services/Object/classes/class.ilObjectAccess.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";
include_once './Services/Conditions/interfaces/interface.ilConditionHandling.php';

/**
* Class ilObjTestAccess
*
* This class contains methods that check object specific conditions
* for accessing test objects.
*
* @author	Helmut Schottmueller <helmut.schottmueller@mac.com>
* @author 	Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
* @ingroup ModulesTest
*/
class ilObjTestAccess extends ilObjectAccess implements ilConditionHandling
{
    public function canBeDelivered(ilWACPath $ilWACPath)
    {
        global $DIC;
        $readable = new Readable($DIC);

        $can_it = $this->findMatch($ilWACPath->getPath(), [
            new AccessFileUploadAnswer($DIC, $readable),
            new AccessQuestionImage($readable),
            new AccessFileUploadPreview($DIC->database(), $DIC->access()),
        ]);

        return !$can_it->isOk() || $can_it->value();
    }

    private function findMatch(string $path, array $array): Result
    {
        return array_reduce($array, static function (Result $result, SimpleAccess $access) use ($path): Result {
            return $result->except(static function () use ($access, $path): Result {
                return $access->isPermitted($path);
            });
        }, new Error('Not a known path.'));
    }

    /**
    * Checks wether a user may invoke a command or not
    * (this method is called by ilAccessHandler::checkAccess)
    *
    * Please do not check any preconditions handled by
    * ilConditionHandler here.
    *
    * @param	string		$a_cmd		command (not permission!)
    * @param	string		$a_permission	permission
    * @param	int			$a_ref_id	reference id
    * @param	int			$a_obj_id	object id
    * @param	int			$a_user_id	user id (if not provided, current user is taken)
    *
    * @return	boolean		true, if everything is ok
    */
    public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = "")
    {
        global $DIC;
        $ilUser = $DIC['ilUser'];
        $lng = $DIC['lng'];
        $rbacsystem = $DIC['rbacsystem'];
        $ilAccess = $DIC['ilAccess'];
        
        if ($a_user_id == "") {
            $a_user_id = $ilUser->getId();
        }
        
        $is_admin = $rbacsystem->checkAccessOfUser($a_user_id, 'write', $a_ref_id);
        

        switch ($a_permission) {
            case "visible":
            case "read":
                if (!ilObjTestAccess::_lookupCreationComplete($a_obj_id) &&
                    !$is_admin) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("tst_warning_test_not_complete"));
                    return false;
                }
                break;
        }
        
        switch ($a_cmd) {
            case "eval_a":
            case "eval_stat":
                if (!ilObjTestAccess::_lookupCreationComplete($a_obj_id)) {
                    $ilAccess->addInfoItem(IL_NO_OBJECT_ACCESS, $lng->txt("tst_warning_test_not_complete"));
                    return false;
                }
                break;

        }

        return true;
    }

    /**
    * Returns TRUE if the user with the user id $user_id passed the test with the object id $a_obj_id
    *
    * @param int $user_id The user id
    * @param int $a_obj_id The object id
    * @return boolean TRUE if the user passed the test, FALSE otherwise
    */
    public static function _isPassed($user_id, $a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $result = $ilDB->queryF(
            "SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
            array('integer','integer'),
            array($user_id, $a_obj_id)
        );
        if (!$result->numRows()) {
            $result = $ilDB->queryF(
                "SELECT tst_active.active_id FROM tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s",
                array('integer','integer'),
                array($user_id, $a_obj_id)
            );
            $row = $ilDB->fetchAssoc($result);
            if ($row['active_id'] > 0) {
                include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                assQuestion::_updateTestResultCache($row['active_id']);
            } else {
                return false;
            }
        }
        $result = $ilDB->queryF(
            "SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
            array('integer','integer'),
            array($user_id, $a_obj_id)
        );
        if (!$result->numRows()) {
            $result = $ilDB->queryF(
                "SELECT tst_pass_result.*, tst_tests.pass_scoring, tst_tests.test_id FROM tst_pass_result, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_pass_result.active_fi = tst_active.active_id ORDER BY tst_pass_result.pass",
                array('integer','integer'),
                array($user_id, $a_obj_id)
            );

            if (!$result->numRows()) {
                return false;
            }

            $points = array();
            while ($row = $ilDB->fetchAssoc($result)) {
                array_push($points, $row);
            }
            $reached = 0;
            $max = 0;
            if ($points[0]["pass_scoring"] == 0) {
                $reached = $points[count($points) - 1]["points"];
                $max = $points[count($points) - 1]["maxpoints"];
                if (!$max) {
                    $active_id = $points[count($points) - 1]["active_fi"];
                    $pass = $points[count($points) - 1]["pass"];
                    if (strlen($active_id) && strlen($pass)) {
                        include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                        $res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
                        $max = $res['maxpoints'];
                        $reached = $res['points'];
                    }
                }
            } else {
                foreach ($points as $row) {
                    if ($row["points"] > $reached) {
                        $reached = $row["points"];
                        $max = $row["maxpoints"];
                        if (!$max) {
                            $active_id = $row["active_fi"];
                            $pass = $row["pass"];
                            if (strlen($active_id) && strlen($pass)) {
                                include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                                $res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
                                $max = $res['maxpoints'];
                                $reached = $res['points'];
                            }
                        }
                    }
                }
            }
            include_once "./Modules/Test/classes/class.assMarkSchema.php";
            $percentage = (!$max) ? 0 : ($reached / $max) * 100.0;
            $mark = ASS_MarkSchema::_getMatchingMarkFromObjId($a_obj_id, $percentage);
            return ($mark["passed"]) ? true : false;
        } else {
            $row = $ilDB->fetchAssoc($result);
            return ($row['passed']) ? true : false;
        }
    }
    
    /**
     * Returns TRUE if the user with the user id $user_id failed the test with the object id $a_obj_id
     *
     * @param int $user_id The user id
     * @param int $a_obj_id The object id
     * @return boolean TRUE if the user failed the test, FALSE otherwise
     */
    public static function isFailed($user_id, $a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        
        $ret = self::updateTestResultCache($user_id, $a_obj_id);

        if (!$ret) {
            return false;
        }
        
        $result = $ilDB->queryF(
            "SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
            array('integer','integer'),
            array($user_id, $a_obj_id)
        );

        if (!$result->numRows()) {
            $result = $ilDB->queryF(
                "SELECT tst_pass_result.*, tst_tests.pass_scoring FROM tst_pass_result, tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s AND tst_pass_result.active_fi = tst_active.active_id ORDER BY tst_pass_result.pass",
                array('integer','integer'),
                array($user_id, $a_obj_id)
            );

            while ($row = $ilDB->fetchAssoc($result)) {
                array_push($points, $row);
            }
            $reached = 0;
            $max = 0;
            if ($points[0]["pass_scoring"] == 0) {
                $reached = $points[count($points) - 1]["points"];
                $max = $points[count($points) - 1]["maxpoints"];
                if (!$max) {
                    $active_id = $points[count($points) - 1]["active_fi"];
                    $pass = $points[count($points) - 1]["pass"];
                    if (strlen($active_id) && strlen($pass)) {
                        include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                        $res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
                        $max = $res['maxpoints'];
                        $reached = $res['points'];
                    }
                }
            } else {
                foreach ($points as $row) {
                    if ($row["points"] > $reached) {
                        $reached = $row["points"];
                        $max = $row["maxpoints"];
                        if (!$max) {
                            $active_id = $row["active_fi"];
                            $pass = $row["pass"];
                            if (strlen($active_id) && strlen($pass)) {
                                include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                                $res = assQuestion::_updateTestPassResults($active_id, $pass, null, $a_obj_id);
                                $max = $res['maxpoints'];
                                $reached = $res['points'];
                            }
                        }
                    }
                }
            }
            include_once "./Modules/Test/classes/class.assMarkSchema.php";
            $percentage = (!$max) ? 0 : ($reached / $max) * 100.0;
            $mark = ASS_MarkSchema::_getMatchingMarkFromObjId($a_obj_id, $percentage);
            return ($mark["failed"]) ? true : false;
        } else {
            $row = $ilDB->fetchAssoc($result);
            return ($row['failed']) ? true : false;
        }
    }
    
    /**
     * Update test result cache
     * @param type $a_user_id
     * @param type $a_obj_id
     */
    protected static function updateTestResultCache($a_user_id, $a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        
        $result = $ilDB->queryF(
            "SELECT tst_result_cache.* FROM tst_result_cache, tst_active, tst_tests " .
                "WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s " .
                "AND tst_tests.obj_fi = %s AND tst_result_cache.active_fi = tst_active.active_id",
            array('integer','integer'),
            array($a_user_id, $a_obj_id)
        );
        if (!$result->numRows()) {
            $result = $ilDB->queryF(
                "SELECT tst_active.active_id FROM tst_active, tst_tests WHERE tst_active.test_fi = tst_tests.test_id AND tst_active.user_fi = %s AND tst_tests.obj_fi = %s",
                array('integer','integer'),
                array($a_user_id, $a_obj_id)
            );
            $row = $ilDB->fetchAssoc($result);
            if ($row['active_id'] > 0) {
                include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                assQuestion::_updateTestResultCache($row['active_id']);
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    
    /**
     * Get possible conditions operators
     */
    public static function getConditionOperators()
    {
        include_once './Services/Conditions/classes/class.ilConditionHandler.php';
        return array(
            ilConditionHandler::OPERATOR_PASSED,
            ilConditionHandler::OPERATOR_FAILED,
            ilConditionHandler::OPERATOR_FINISHED,
            ilConditionHandler::OPERATOR_NOT_FINISHED
        );
    }
    

    /**
    * check condition
    *
    * this method is called by ilConditionHandler
    */
    public static function checkCondition($a_obj_id, $a_operator, $a_value, $a_usr_id)
    {
        include_once './Services/Conditions/classes/class.ilConditionHandler.php';
        
        switch ($a_operator) {
            case ilConditionHandler::OPERATOR_PASSED:
                return ilObjTestAccess::_isPassed($a_usr_id, $a_obj_id);
                break;
            
            case ilConditionHandler::OPERATOR_FAILED:
                return ilObjTestAccess::isFailed($a_usr_id, $a_obj_id);

            case ilConditionHandler::OPERATOR_FINISHED:
                return ilObjTestAccess::hasFinished($a_usr_id, $a_obj_id);

            case ilConditionHandler::OPERATOR_NOT_FINISHED:
                return !ilObjTestAccess::hasFinished($a_usr_id, $a_obj_id);

            default:
                return true;
        }
        return true;
    }

    /**
     * get commands
     *
     * this method returns an array of all possible commands/permission combinations
     *
     * example:
     * $commands = array
     *	(
     *		array("permission" => "read", "cmd" => "view", "lang_var" => "show"),
     *		array("permission" => "write", "cmd" => "edit", "lang_var" => "edit"),
     *	);
     */
    public static function _getCommands()
    {
        global $DIC;
        $DIC->language()->loadLanguageModule('assessment');

        $commands = array(
            array("permission" => "write", "cmd" => "questionsTabGateway", "lang_var" => "tst_edit_questions"),
            array("permission" => "write", "cmd" => "ilObjTestSettingsGeneralGUI::showForm", "lang_var" => "settings"),
            array("permission" => "read", "cmd" => "infoScreen", "lang_var" => "tst_run",
                "default" => true),
            //array("permission" => "write", "cmd" => "", "lang_var" => "edit"),
            array("permission" => "tst_statistics", "cmd" => "outEvaluation", "lang_var" => "tst_statistical_evaluation"),
            array("permission" => "read", "cmd" => "userResultsGateway", "lang_var" => "tst_user_results"),
            array("permission" => "write", "cmd" => "testResultsGateway", "lang_var" => "results"),
            array("permission" => "eval_a", "cmd" => "testResultsGateway", "lang_var" => "results")
        );
        
        return $commands;
    }

    //
    // object specific access related methods
    //

    /**
    * checks wether all necessary parts of the test are given
    */
    public static function _lookupCreationComplete($a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT complete FROM tst_tests WHERE obj_fi=%s",
            array('integer'),
            array($a_obj_id)
        );
        if ($result->numRows() == 1) {
            $row = $ilDB->fetchAssoc($result);
        }

        return ($row['complete']) ? true : false;
    }

    /**
     * Request Cache for hasFinished Information
     *
     * @var array
     */
    private static $hasFinishedCache = array();

    /**
     * Returns (request cached) information if a specific user has finished at least one test pass
     *
     * @param integer $a_user_id obj_id of the user
     * @param integer $a_obj_id obj_id of the test
     * @return bool
     */
    public static function hasFinished($a_user_id, $a_obj_id)
    {
        if (!isset(self::$hasFinishedCache["{$a_user_id}:{$a_obj_id}"])) {
            require_once 'Modules/Test/classes/class.ilTestParticipantData.php';
            require_once 'Modules/Test/classes/class.ilTestSessionFactory.php';
            require_once 'Modules/Test/classes/class.ilTestPassesSelector.php';
            
            global $DIC;
            $ilDB = $DIC['ilDB'];
            $lng = $DIC['lng'];
            
            $testOBJ = ilObjectFactory::getInstanceByObjId($a_obj_id);
            
            $partData = new ilTestParticipantData($ilDB, $lng);
            $partData->setUserIdsFilter(array($a_user_id));
            $partData->load($testOBJ->getTestId());
            
            $activeId = $partData->getActiveIdByUserId($a_user_id);

            $testSessionFactory = new ilTestSessionFactory($testOBJ);
            $testSession = $testSessionFactory->getSession($activeId);
            
            $testPassesSelector = new ilTestPassesSelector($ilDB, $testOBJ);
            $testPassesSelector->setActiveId($activeId);
            $testPassesSelector->setLastFinishedPass($testSession->getLastFinishedPass());
            
            self::$hasFinishedCache["{$a_user_id}:{$a_obj_id}"] = count($testPassesSelector->getClosedPasses());
        }
        
        return (bool) self::$hasFinishedCache["{$a_user_id}:{$a_obj_id}"];
    }

    /**
    * Returns the ILIAS test id for a given object id
    *
    * @param integer $object_id The object id
    * @return mixed The ILIAS test id or FALSE if the query was not successful
    * @access public
    */
    public static function _getTestIDFromObjectID($object_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $test_id = false;
        $result = $ilDB->queryF(
            "SELECT test_id FROM tst_tests WHERE obj_fi = %s",
            array('integer'),
            array($object_id)
        );
        if ($result->numRows()) {
            $row = $ilDB->fetchAssoc($result);
            $test_id = $row["test_id"];
        }
        return $test_id;
    }

    /**
     * Lookup object id for test id
     *
     * @param		int		test id
     * @return		int		object id
     */
    public static function _lookupObjIdForTestId($a_test_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT obj_fi FROM tst_tests WHERE test_id = %s",
            array('integer'),
            array($a_test_id)
        );

        $row = $ilDB->fetchAssoc($result);
        return $row["obj_fi"];
    }

    /**
    * Get all tests using a question pool for random selection
    *
    * @param    int     question pool id
    * @return 	array 	list if test obj ids
    * @access	public
    */
    public static function _getRandomTestsForQuestionPool($qpl_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
    
        $query = "
			SELECT DISTINCT t.obj_fi
			FROM tst_tests t
			INNER JOIN tst_rnd_quest_set_qpls r
			ON t.test_id = r.test_fi
			WHERE r.pool_fi = %s
		";
    
        $result = $ilDB->queryF($query, array('integer'), array($qpl_id));
    
        $tests = array();
        while ($row = $ilDB->fetchAssoc($result)) {
            $tests[] = $row['obj_fi'];
        }
    
        return $tests;
    }
    // fim.

    // uzk-patch (extended test ip filter): begin
    /**
     * Checks if a user is allowed to run an online exam
     *
     * @param int $a_test_id The test ID
     * @param int $a_user_id The user ID
     * @return bool|string True if the user is allowed, an alert message otherwise
     */
    public static function _lookupOnlineTestAccess($a_test_id, $a_user_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];
        $lng = $DIC['lng'];

        // Query tst_tests table to get test details, including client IP restriction
        $result = $ilDB->queryF(
            "SELECT * FROM tst_tests WHERE tst_tests.obj_fi = %s",
            ['integer'],
            [$a_test_id]
        );

        if ($result->numRows()) {
            $row = $ilDB->fetchAssoc($result);
            $test_clientip = isset($row['clientip_filter']) ? $row['clientip_filter'] : null;

            // Check if the test has fixed participants
            if ($row["fixed_participants"]) {
                // Query tst_invited_user table to check if the user is invited
                $result = $ilDB->queryF(
                    "SELECT * FROM tst_invited_user WHERE test_fi = %s AND user_fi = %s",
                    ['integer', 'integer'],
                    [$row["test_id"], $a_user_id]
                );

                if ($result->numRows()) {
                    $invited_row = $ilDB->fetchAssoc($result);
                    $invited_clientip = $invited_row['clientip'];

                    // Check if client IP is set for the invited user and validate it
                    if ($invited_clientip !== null && trim($invited_clientip) != "") {
                        if (!self::isClientIpAllowed($invited_clientip, $_SERVER["REMOTE_ADDR"])) {
                            $lng->loadLanguageModule('assessment');
                            return $lng->txt("user_wrong_clientip");
                        } else {
                            return true;
                        }
                        // If no client IP is set for the invited user, check against test-level client IP
                    } elseif ($test_clientip !== null && trim($test_clientip) != "") {
                        if (!self::isClientIpAllowed($test_clientip, $_SERVER["REMOTE_ADDR"])) {
                            $lng->loadLanguageModule('assessment');
                            return $lng->txt("user_wrong_clientip");
                        } else {
                            return true;
                        }
                    } else {
                        return true;
                    }
                } else {
                    return $lng->txt("tst_user_not_invited");
                }
                // If the test does not have fixed participants, check against test-level client IP if set
            } elseif ($test_clientip !== null && trim($test_clientip) != "") {
                if (!self::isClientIpAllowed($test_clientip, $_SERVER["REMOTE_ADDR"])) {
                    $lng->loadLanguageModule('assessment');
                    return $lng->txt("user_wrong_clientip");
                } else {
                    return true;
                }
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * Checks if the client's IP address matches the allowed IP pattern
     *
     * @param string $allowed_ip The allowed IP pattern (single IP, list, range, CIDR)
     * @param string $client_ip The client's IP address
     * @return bool True if the client's IP matches, false otherwise
     */
    private static function isClientIpAllowed($allowed_ip, $client_ip)
    {
        $ip_patterns = preg_split('/[,;]/', $allowed_ip);
        foreach ($ip_patterns as $ip_pattern) {
            $ip_pattern = trim($ip_pattern);

            // Handle CIDR format
            if (strpos($ip_pattern, '/') !== false) {
                if (self::checkCidr($ip_pattern, $client_ip)) {
                    return true;
                }
                continue;
            }

            // Handle range format
            if (strpos($ip_pattern, '-') !== false) {
                if (self::checkIpRange($ip_pattern, $client_ip)) {
                    return true;
                }
                continue;
            }

            // Sanitize the allowed IP pattern
            $ip_pattern = preg_replace("/[^0-9.?*:\/\\-]+/", "", $ip_pattern);
            $ip_pattern = str_replace(".", "\\.", $ip_pattern);
            $ip_pattern = str_replace(["?", "*"], ["[0-9]", "[0-9]*"], $ip_pattern);

            // Match the client IP with the allowed IP pattern
            if (preg_match("/^" . $ip_pattern . "$/", $client_ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if an IP address is within a CIDR range
     *
     * @param string $cidr The CIDR range
     * @param string $ip The IP address to check
     * @return bool True if the IP is within the range, false otherwise
     */
    private static function checkCidr($cidr, $ip)
    {
        list($subnet, $bits) = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; // Align subnet to mask

        return ($ip & $mask) === $subnet;
    }

    /**
     * Checks if an IP address is within a given range
     *
     * @param string $range The IP range in the format 'start_ip-end_ip'
     * @param string $ip The IP address to check
     * @return bool True if the IP is within the range, false otherwise
     */
    private static function checkIpRange($range, $ip)
    {
        list($start_ip, $end_ip) = explode('-', $range);
        $ip_long = ip2long($ip);
        $start_ip_long = ip2long($start_ip);
        $end_ip_long = ip2long($end_ip);

        return ($ip_long >= $start_ip_long && $ip_long <= $end_ip_long);
    }
    // uzk-patch (extended test ip filter): end

    /**
    * Retrieves a participant name from active id
    *
    * @param integer $active_id Active ID of the participant
    * @return string The output name of the user
    * @access public
    */
    public static function _getParticipantData($active_id)
    {
        global $DIC;
        $lng = $DIC['lng'];
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT * FROM tst_active WHERE active_id = %s",
            array("integer"),
            array($active_id)
        );
        $row = $ilDB->fetchAssoc($result);
        $user_id = $row["user_fi"];
        $test_id = $row["test_fi"];
        $importname = $row['importname'];

        $result = $ilDB->queryF(
            "SELECT obj_fi FROM tst_tests WHERE test_id = %s",
            array("integer"),
            array($test_id)
        );
        $row = $ilDB->fetchAssoc($result);
        $obj_id = $row["obj_fi"];
        
        include_once "./Modules/Test/classes/class.ilObjTest.php";
        $is_anonymous = ilObjTest::_lookupAnonymity($obj_id);
        
        include_once './Services/User/classes/class.ilObjUser.php';
        $uname = ilObjUser::_lookupName($user_id);

        $name = "";
        if (strlen($importname)) {
            $name = $importname . ' (' . $lng->txt('imported') . ')';
        } elseif (strlen($uname["firstname"] . $uname["lastname"]) == 0) {
            $name = $lng->txt("deleted_user");
        } else {
            if ($user_id == ANONYMOUS_USER_ID) {
                $name = $lastname;
            } else {
                $name = trim($uname["lastname"] . ", " . $uname["firstname"]);
            }
            if ($is_anonymous) {
                $name = $lng->txt("anonymous");
            }
        }
        return $name;
    }

    /**
     * Get user id for active id
     *
     * @param	int		active ID of the participant
     * @return	int		user id
     */
    public static function _getParticipantId($active_id)
    {
        global $DIC;
        $lng = $DIC['lng'];
        $ilDB = $DIC['ilDB'];

        $result = $ilDB->queryF(
            "SELECT user_fi FROM tst_active WHERE active_id = %s",
            array("integer"),
            array($active_id)
        );
        $row = $ilDB->fetchAssoc($result);
        return $row["user_fi"];
    }


    /**
    * Returns an array containing the users who passed the test
    *
    * @return array An array containing the users who passed the test.
    *         Format of the values of the resulting array:
    *           array(
    *             "user_id"        => user ID,
    *             "max_points"     => maximum available points in the test
    *             "reached_points" => maximum reached points of the user
    *             "mark_short"     => short text of the passed mark
    *             "mark_official"  => official text of the passed mark
    *           )
    * @access public
    */
    public static function _getPassedUsers($a_obj_id)
    {
        global $DIC;
        $ilDB = $DIC['ilDB'];

        $passed_users = array();
        // Maybe SELECT DISTINCT(tst_active.user_fi)... ?
        $userresult = $ilDB->queryF(
            "
			SELECT tst_active.active_id, COUNT(tst_sequence.active_fi) sequences, tst_active.last_finished_pass,
				CASE WHEN
					(tst_tests.nr_of_tries - 1) = tst_active.last_finished_pass
				THEN '1'
				ELSE '0'
				END is_last_pass
			FROM tst_tests
			INNER JOIN tst_active
			ON tst_active.test_fi = tst_tests.test_id
			LEFT JOIN tst_sequence
			ON tst_sequence.active_fi = tst_active.active_id
			WHERE tst_tests.obj_fi = %s
			GROUP BY tst_active.active_id
			",
            array('integer'),
            array($a_obj_id)
        );
        $all_participants = array();
        $notAttempted = array();
        $lastPassUsers = array();
        while ($row = $ilDB->fetchAssoc($userresult)) {
            if ($row['sequences'] == 0) {
                $notAttempted[$row['active_id']] = $row['active_id'];
            }
            if ($row['is_last_pass']) {
                $lastPassUsers[$row['active_id']] = $row['active_id'];
            }

            $all_participants[$row['active_id']] = $row['active_id'];
        }
        
        $result = $ilDB->query("SELECT tst_result_cache.*, tst_active.user_fi FROM tst_result_cache, tst_active WHERE tst_active.active_id = tst_result_cache.active_fi AND " . $ilDB->in('active_fi', $all_participants, false, 'integer'));
        $found_all = ($result->numRows() == count($all_participants)) ? true : false;
        if (!$found_all) {
            // if the result cache entries do not exist, create them
            $found_participants = array();
            while ($data = $ilDB->fetchAssoc($result)) {
                array_push($found_participants, $data['active_fi']);
            }
            foreach ($all_participants as $active_id) {
                if (!in_array($active_id, $found_participants)) {
                    include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
                    assQuestion::_updateTestResultCache($active_id);
                }
            }
            $result = $ilDB->query("SELECT tst_result_cache.*, tst_active.user_fi FROM tst_result_cache, tst_active WHERE tst_active.active_id = tst_result_cache.active_fi AND " . $ilDB->in('active_fi', $all_participants, false, 'integer'));
        }
        while ($data = $ilDB->fetchAssoc($result)) {
            if (isset($notAttempted[$data['active_fi']])) {
                $data['failed'] = 0;
                $data['passed'] = 0;
                $data['not_attempted'] = 1;
            }
            
            if ($data['failed'] && !isset($lastPassUsers[$data['active_fi']])) {
                $data['passed'] = 0;
                $data['failed'] = 0;
                $data['in_progress'] = 1;
            }

            $data['user_id'] = $data['user_fi'];
            array_push($passed_users, $data);
        }
        return $passed_users;
    }

    /**
    * check whether goto script will succeed
    */
    public static function _checkGoto($a_target)
    {
        global $DIC;
        $ilAccess = $DIC['ilAccess'];
        
        $t_arr = explode("_", $a_target);

        if ($t_arr[0] != "tst" || ((int) $t_arr[1]) <= 0) {
            return false;
        }

        if ($ilAccess->checkAccess("read", "", $t_arr[1]) ||
            $ilAccess->checkAccess("visible", "", $t_arr[1])) {
            return true;
        }
        return false;
    }

    /**
     * returns the objects's OFFline status
     *
     * Used in ListGUI and Learning Progress
     *
     * @param int $a_obj_id
     * @return bool
     */
    public static function _isOffline($a_obj_id)
    {
        //		global $DIC;
        //		$ilUser = $DIC['ilUser'];
        //		return (self::_lookupOnlineTestAccess($a_obj_id, $ilUser->getId()) !== true) ||
        //			(!ilObjTestAccess::_lookupCreationComplete($a_obj_id));
        return ilObject::lookupOfflineStatus($a_obj_id);
    }


    public static function visibleUserResultExists($testObjId, $userId)
    {
        $testOBJ = ilObjectFactory::getInstanceByObjId($testObjId, false);

        if (!($testOBJ instanceof ilObjTest)) {
            return false;
        }

        require_once 'Modules/Test/classes/class.ilTestSessionFactory.php';
        $testSessionFactory = new ilTestSessionFactory($testOBJ);
        $testSession = $testSessionFactory->getSessionByUserId($userId);

        return $testOBJ->canShowTestResults($testSession);
    }
    
    /**
     * @ideaof Andre Michels <amichels@databay.de>
     *
     * @param $testObjId
     * @param $userId
     * @return bool
     */
    public static function hasVisibleCertificate($testObjId, $userId)
    {
        $testOBJ = ilObjectFactory::getInstanceByObjId($testObjId, false);
        
        if (!($testOBJ instanceof ilObjTest) || !$userId) {
            return false;
        }
        
        require_once 'Modules/Test/classes/class.ilTestSessionFactory.php';
        $testSessionFactory = new ilTestSessionFactory($testOBJ);
        $testSession = $testSessionFactory->getSessionByUserId($userId);
        
        if (!$testSession->getActiveId()) {
            return false;
        }
        
        return $testOBJ->canShowCertificate($testSession, $testSession->getUserId(), $testSession->getActiveId());
    }
}
