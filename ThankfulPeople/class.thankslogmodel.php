<?php if (!defined('APPLICATION')) exit();

/**
 * Class ThankfulLogModel
 *
 * @author Jerl Liandri
 * @author Chris Ireland <ireland63@gmal.com>
 * @license MIT <opensource.org/licenses/MIT> & X.Net <http://opensource.org/licenses/xnet.php>
 */
class ThanksLogModel extends Gdn_Model
{

    protected static $TableFields = array(
        'comment' => 'CommentID',
        'discussion' => 'DiscussionID'
    );

    protected static $TableNames = array();

    /**
     * Create a new instance of our module
     */
    public function __construct()
    {
        parent::__construct('ThanksLog');
        $this->FireEvent('AfterConstruct');
    }

    /**
     * Get a user's collated thanks
     *
     * @param bool $Where
     * @return mixed
     */
    public function GetCount($Where = False)
    {
        $Where['bCountQuery'] = True;
        $Result = $this->Get($Where);
        return $Result;
    }

    /**
     * Gets the thanks for a user
     *
     * @param bool $Where
     * @param bool $Offset
     * @param bool $Limit
     * @return mixed
     */
    public function Get($Where = False, $Offset = False, $Limit = False)
    {

        $bCountQuery = GetValue('bCountQuery', $Where, False, True);
        $this->EventArguments['WhereOptions'] = $Where;
        $this->EventArguments['bCountQuery'] = $bCountQuery;

        if ($bCountQuery) {
            $this->SQL->Select('*', 'count', 'RowCount');
            $Offset = $Limit = False;
        }
        if ($CommentData = GetValue('Comments', $Where, False, True)) {
            if ($CommentData instanceof Gdn_DataSet) $CommentData = ConsolidateArrayValuesByKey($CommentData->Result(), 'CommentID');
            if (!is_array($CommentData)) trigger_error('Unexpected type: ' . gettype($CommentData), E_USER_ERROR);
            $this->SQL
                ->WhereIn('t.CommentID', $CommentData);
        }
        if ($WithDiscussionID = GetValue('WithDiscussionID', $Where, False, True)) {
            $this->SQL->OrWhere('t.DiscussionID', $WithDiscussionID);
        }

        $this->FireEvent('BeforeGet');

        // Final where and return dataset or row count
        if (is_array($Where)) $this->SQL->Where($Where);
        $Result = $this->SQL
            ->From('ThanksLog t')
            ->Limit($Limit, $Offset)
            ->Get();
        if ($bCountQuery) $Result = $Result->FirstRow()->RowCount;
        return $Result;
    }

    /**
     * Get the primary field key
     *
     * @param $Name
     * @return string
     */
    public static function GetPrimaryKeyField($Name)
    { // Type, Table name
        $Name = strtolower($Name);
        if (array_key_exists($Name, self::$TableFields)) return self::$TableFields[$Name];
        return self::GetTableName($Name) . 'ID';
    }

    /**
     * Get the table name
     *
     * @param $Name
     * @return mixed
     */
    public static function GetTableName($Name)
    {
        $Name = strtolower($Name);
        return ArrayValue($Name, self::$TableNames, ucfirst($Name));
    }

    /**
     * Get the relevant user id
     *
     * @param $Name
     * @param $ObjectID
     * @return mixed
     */
    public static function GetObjectInsertUserID($Name, $ObjectID)
    {
        $Field = self::GetPrimaryKeyField($Name);
        $Table = self::GetTableName($Name);
        $UserID = Gdn::SQL()
            ->Select('InsertUserID')
            ->From($Table)
            ->Where($Field, (int)$ObjectID, False, False)
            ->Get()
            ->Value('InsertUserID');
        return $UserID;
    }

    /**
     * Remove a thank  from the database
     *
     * @param $Type
     * @param $ObjectID
     * @param $SessionUserID
     */
    public static function RemoveThank($Type, $ObjectID, $SessionUserID)
    {
        $Field = self::GetPrimaryKeyField($Type);
        $UserID = self::GetObjectInsertUserID($Type, $ObjectID);
        $SQL = Gdn::SQL();
        $SQL
            ->Where($Field, $ObjectID)
            ->Where('InsertUserID', $SessionUserID)
            ->Limit(1)
            ->Delete('ThanksLog');
        self::UpdateUserReceivedThankCount($UserID, '-1');
    }

    /**
     * Store a thank in the database
     *
     * @param $Type
     * @param $ObjectID
     * @param $UserID
     */
    public static function PutThank($Type, $ObjectID, $UserID)
    {
        $Field = self::GetPrimaryKeyField($Type);
        $SQL = Gdn::SQL();
        $SQL
            ->History(False, True)
            ->Set($Field, $ObjectID)
            ->Set('UserID', $UserID)
            ->Insert('ThanksLog', array());
        self::UpdateUserReceivedThankCount($UserID);
    }

    /**
     * Increment/decrement a user's thanks
     *
     * @param $UserID
     * @param string $Value
     */
    public static function UpdateUserReceivedThankCount($UserID, $Value = '+1')
    {
        if (!in_array($Value, array('-1', '+1'))) $Value = '+1';
        Gdn::SQL()
            ->Update('User')
            ->Set('ReceivedThankCount', 'ReceivedThankCount' . $Value, False)
            ->Where('UserID', $UserID)
            ->Put();
    }

    /**
     * Recalculate the cache for a user's received thanks
     */
    public static function RecalculateUserReceivedThankCount()
    {
        $SQL = Gdn::SQL();
        $SqlCount = $SQL
            ->Select('*', 'count', 'Count')
            ->From('ThanksLog t')
            ->Where('t.UserID', 'u.UserID', False, False)
            ->GetSelect();
        $SQL->Reset();
        $SQL
            ->Update('User u')
            ->Set('u.ReceivedThankCount', "($SqlCount)", False, False)
            ->Put();
    }

    /**
     * Get the comments in a disscussion
     *
     * @param $DiscussionID
     * @param $CommentData
     * @param null $Where
     * @return mixed
     */
    public function GetDiscussionComments($DiscussionID, $CommentData, $Where = Null)
    {
        $Where['WithDiscussionID'] = $DiscussionID;
        $Result = $this->GetComments($CommentData, $Where);
        return $Result;
    }

    /**
     * A scaffhold for queries
     */
    public function BaseQuery()
    {
        $this->SQL
            ->Select('t.CommentID, t.DiscussionID, t.DateInserted, t.InsertUserID as UserID, u.Name')
            ->Join('User u', 'u.UserID = t.InsertUserID', 'inner');
    }

    /**
     * Fetch plugin data
     *
     * @param $Type
     * @param $ObjectID
     * @return mixed
     */
    public function GetThankfulPeople($Type, $ObjectID)
    {
        $this->BaseQuery();
        $Field = self::GetPrimaryKeyField($Type);
        $Result = $this->Get(array($Field => $ObjectID));
        return $Result;
    }

    /**
     * Fetch comment data
     *
     * @param $CommentData
     * @param null $Where
     * @return mixed
     */
    public function GetComments($CommentData, $Where = Null)
    {
        $Where['Comments'] = $CommentData;
        $this->BaseQuery();
        $Result = $this->Get($Where);
        return $Result;
    }

    /**
     * Log received thanks
     *
     * @param bool $Where
     * @param bool $Offset
     * @param bool $Limit
     * @return array
     */
    public function GetReceivedThanks($Where = False, $Offset = False, $Limit = False)
    {
        $this->BaseQuery();
        $this->SQL
            ->OrderBy('t.DateInserted', 'desc');
        $ReceivedThanks = $this->Get($Where, $Offset, $Limit);
        $ThankData = array();
        $this->EventArguments['ReceivedThanks'] = $ReceivedThanks;
        $this->EventArguments['ThankData'] =& $ThankData;
        while ($Data = $ReceivedThanks->NextRow()) {
            if ($Data->CommentID > 0) $ThankData['Comment'][$Data->CommentID][] = $Data;
            elseif ($Data->DiscussionID > 0) $ThankData['Discussion'][$Data->DiscussionID][] = $Data;
        }
        $this->FireEvent('BeforeRetreiveThankObjects');

        if (count($ThankData) == 0) return array(array(), array());
        foreach (array_keys($ThankData) as $Type) {
            $ObjectIDs = array_keys($ThankData[$Type]);
            $ObjectPrimaryKey = self::GetPrimaryKeyField($Type);
            $Table = self::GetTableName($Type);
            $ExcerptTextField = 'Body';
            switch ($Table) {
                case 'Comment':
                    $this->SQL->Select('CommentID', "concat('discussion/comment/', %s)", 'Url');
                    break;
                case 'Discussion':
                    $this->SQL->Select('DiscussionID', "concat('discussion/', %s)", 'Url');
                    break;
            }
            $this->EventArguments['ObjectPrimaryKey'] =& $ObjectPrimaryKey;
            $this->EventArguments['ObjectTable'] =& $Table;
            $this->EventArguments['ExcerptTextField'] =& $ExcerptTextField;
            $this->FireEvent('RetreiveThankObject');

            $ObjectIDs = implode(',', array_map('intval', $ObjectIDs));

            $Sql = $this->SQL
                ->Select("'$Type'", '', 'Type')
                ->Select($ObjectPrimaryKey, '', 'ObjectID')
                ->Select($ExcerptTextField, 'mid(%s, 1, 255)', 'ExcerptText')
                ->Select('DateInserted')
                ->From($Table)
                ->Where($ObjectPrimaryKey . ' in (' . $ObjectIDs . ')', Null, False, False)
                ->GetSelect();
            $this->SQL->Reset();
            $SqlCollection[] = $Sql;
        }

        $this->EventArguments['SqlCollection'] =& $SqlCollection;
        $this->FireEvent('AfterRetreiveThankObjects');

        $ResultSql = implode("\n union \n", $SqlCollection);
        $Objects = $this->SQL->Query("select * from (\n$ResultSql\n) as t order by DateInserted desc")->Result();
        $Result = array($ThankData, $Objects);
        return $Result;
    }

    /**
     * Clean up the log database
     */
    public static function CleanUp()
    {
        $SQL = Gdn::SQL();
        $Px = $SQL->Database->DatabasePrefix;
        $SQL->Query("delete t.* from {$Px}ThanksLog t
			left join {$Px}Comment c on c.CommentID = t.CommentID 
			where c.commentID is null and t.commentID > 0");
        $SQL->Query("delete t.* from {$Px}ThanksLog t
			left join {$Px}Discussion d on d.DiscussionID = t.DiscussionID 
			where d.DiscussionID is null and t.DiscussionID > 0");
    }

}