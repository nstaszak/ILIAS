<#1>
<?php
if( !$ilDB->tableColumnExists('tst_tests', 'clientip_filter') )
{
    $ilDB->addTableColumn('tst_tests', 'clientip_filter', array(
        'type' => 'text',
        'notnull' => false,
        'length' => 100,
        'default' => null
    ));
}
if( !$ilDB->tableColumnExists('tst_tests', 'clientip_filter_enabled') )
{
    $ilDB->addTableColumn('tst_tests', 'clientip_filter_enabled', array(
        'type' => 'integer',
        'notnull' => false,
        'length' => 1,
        'default' => 0
    ));
}
?>