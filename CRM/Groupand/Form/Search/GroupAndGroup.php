<?php

class CRM_Groupand_Form_Search_GroupAndGroup extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  protected $_formValues;

  protected $_tableName = NULL;

  protected $_where = ' (1) ';

  protected $_aclFrom = NULL;
  protected $_aclWhere = NULL;

  /**
   * Class constructor.
   *
   * @param array $formValues
   */
  public function __construct(&$formValues) {
    $this->_formValues = $formValues;
    $this->_columns = array(
      ts('Contact ID') => 'contact_id',
      ts('Contact Type') => 'contact_type',
      ts('Name') => 'sort_name',
      ts('Group Name') => 'gname',
    );

    $this->_includeGroupsA = CRM_Utils_Array::value('includeGroupsA', $this->_formValues, array());
    $this->_includeGroupsB = CRM_Utils_Array::value('includeGroupsB', $this->_formValues, array());

    //define variables
    $this->_allSearch = FALSE;
    $this->_groups = FALSE;
    $this->_andOr = CRM_Utils_Array::value('andOr', $this->_formValues);
    //make easy to check conditions for groups and tags are
    //selected or it is empty search
    if (empty($this->_includeGroupsA) && empty($this->_includeGroupsB)
    ) {
      //empty search
      $this->_allSearch = TRUE;
    }

    $this->_groups = (!empty($this->_includeGroupsA) || !empty($this->_includeGroupsB));
  }

  public function __destruct() {
    // mysql drops the tables when connection is terminated
    // cannot drop tables here, since the search might be used
    // in other parts after the object is destroyed
  }

  /**
   * @param CRM_Core_Form $form
   */
  public function buildForm(&$form) {

    $this->setTitle(ts('Search contacts in both groups'));

    $groups = CRM_Core_PseudoConstant::nestedGroup();


    $select2style = array(
      'multiple' => TRUE,
      'style' => 'width: 100%; max-width: 60em;',
      'class' => 'crm-select2',
      'placeholder' => ts('- select -'),
    );

    $form->add('select', 'includeGroupsA',
      ts('Include Group(s)'),
      $groups,
      FALSE,
      $select2style
    );

    $form->add('select', 'includeGroupsB',
      ts('AND in Group(s)'),
      $groups,
      FALSE,
      $select2style
    );


    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
    $form->assign('elements', array('includeGroupsA', 'includeGroupsB'));
  }

  /**
   * @param int $offset
   * @param int $rowcount
   * @param NULL $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   *
   * @return string
   */
  public function all(
// XAV
//  select * from civicrm_group_contact cg1 join civicrm_group_contact cg2 where cg1.group_id in (groupsA) and cg2.group_id in (groupsB) and cg1.contact_id=cg2.contact_id


    $offset = 0, $rowcount = 0, $sort = NULL,
    $includeContactIDs = FALSE, $justIDs = FALSE
  ) {

    if ($justIDs) {
      $selectClause = "contact_a.id as contact_id";
    }
    else {
      $selectClause = "contact_a.id as contact_id,
                         contact_a.contact_type as contact_type,
                         contact_a.sort_name    as sort_name";

    }

    $from = $this->from();

    $where = $this->where($includeContactIDs);

    if (!$justIDs && !$this->_allSearch) {
      $groupBy = " GROUP BY contact_a.id";
    }
    else {
      // CRM-10850
      // we do this since this if stmt is called by the smart group part of the code
      // adding a groupBy clause and saving it as a smart group messes up the query and
      // bad things happen
      // andrew hunt seemed to have rewritten this piece when he worked on this search
      $groupBy = NULL;
    }

    $sql = "SELECT $selectClause $from WHERE  $where $groupBy";

    // Define ORDER BY for query in $sort, with default value
    if (!$justIDs) {
      if (!empty($sort)) {
        if (is_string($sort)) {
          $sort = CRM_Utils_Type::escape($sort, 'String');
          $sql .= " ORDER BY $sort ";
        }
        else {
          $sql .= " ORDER BY " . trim($sort->orderBy());
        }
      }
      else {
        $sql .= " ORDER BY contact_id ASC";
      }
    }
    else {
      $sql .= " ORDER BY contact_a.id ASC";
    }

    if ($offset >= 0 && $rowcount > 0) {
      $sql .= " LIMIT $offset, $rowcount ";
    }

die ($sql);
    return $sql;
  }

  /**
   * @return string
   * @throws Exception
   */
  public function from() {
//  select * from civicrm_group_contact cg1 join civicrm_group_contact cg2 where cg1.group_id in (groupsA) and cg2.group_id in (groupsB) and cg1.contact_id=cg2.contact_id

      if (is_array($this->_includeGroupsA)) {
        $xGroups = implode(',', $this->_includeGroupsA);
      }
      else {
        $xGroups = 0;
      }
      if (is_array($this->_includeGroupsB)) {
        $iGroups = implode(',', $this->_includeGroupsB);
      }
      else {
        $iGroups = 0;
      }


      if (true ==="XAV" && $xGroups != 0) {
        //search for smart group contacts
        foreach ($this->_includeGroupsB as $keys => $values) {
          if (in_array($values, $smartGroup)) {
            $ssGroup = new CRM_Contact_DAO_Group();
            $ssGroup->id = $values;
            if (!$ssGroup->find(TRUE)) {
              CRM_Core_Error::fatal();
            }
            CRM_Contact_BAO_GroupContactCache::load($ssGroup);

            $smartSql = "in $ssGroup";
          }
        }
      }


   $from ="civicrm_group_contact cg1 join civicrm_group_contact cg2 where cg1.group_id in ($xGroups) and cg2.group_id in ($iGroups) and cg1.contact_id=cg2.contact_id";

    $from .= " LEFT JOIN civicrm_email ON ( contact_a.id = civicrm_email.contact_id AND ( civicrm_email.is_primary = 1 OR civicrm_email.is_bulkmail = 1 ) ) {$this->_aclFrom}";

    if ($this->_aclWhere) {
      $this->_where .= " AND {$this->_aclWhere} ";
    }

    // also exclude all contacts that are deleted
    // CRM-11627
    $this->_where .= " AND (contact_a.is_deleted != 1) ";

die ($from);
    return $from;
  }

  /**
   * @param bool $includeContactIDs
   *
   * @return string
   */
  public function where($includeContactIDs = FALSE) {
    if ($includeContactIDs) {
      $contactIDs = array();

      foreach ($this->_formValues as $id => $value) {
        if ($value &&
          substr($id, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX
        ) {
          $contactIDs[] = substr($id, CRM_Core_Form::CB_PREFIX_LEN);
        }
      }

      if (!empty($contactIDs)) {
        $contactIDs = implode(', ', $contactIDs);
        $clauses[] = "contact_a.id IN ( $contactIDs )";
      }
      $where = "{$this->_where} AND " . implode(' AND ', $clauses);
    }
    else {
      $where = $this->_where;
    }

    return $where;
  }

  /*
   * Functions below generally don't need to be modified
   */

  /**
   * @inheritDoc
   */
  public function count() {
    $sql = $this->all();

    $dao = CRM_Core_DAO::executeQuery($sql);
    return $dao->N;
  }

  /**
   * @param int $offset
   * @param int $rowcount
   * @param NULL $sort
   * @param bool $returnSQL
   *
   * @return string
   */
  public function contactIDs($offset = 0, $rowcount = 0, $sort = NULL, $returnSQL = FALSE) {
    return $this->all($offset, $rowcount, $sort, FALSE, TRUE);
  }

  /**
   * Define columns.
   *
   * @return array
   */
  public function &columns() {
    return $this->_columns;
  }

  /**
   * Get summary.
   *
   * @return NULL
   */
  public function summary() {
    return NULL;
  }

  /**
   * Get template file.
   *
   * @return string
   */
  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Set title on search.
   *
   * @param string $title
   */
  public function setTitle($title) {
    if ($title) {
      CRM_Utils_System::setTitle($title);
    }
    else {
      CRM_Utils_System::setTitle(ts('Search'));
    }
  }

  /**
   * Build ACL clause.
   *
   * @param string $tableAlias
   */
  public function buildACLClause($tableAlias = 'contact') {
    list($this->_aclFrom, $this->_aclWhere) = CRM_Contact_BAO_Contact_Permission::cacheClause($tableAlias);
  }

}
