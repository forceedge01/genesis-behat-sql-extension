<?php

namespace Genesis\SQLExtension\Tests\Unit\Context;

use Exception;
use Genesis\SQLExtension\Context\API;
use Genesis\SQLExtension\Context\Interfaces\DBManagerInterface;
use Genesis\SQLExtension\Context\Interfaces\DatabaseProviderInterface;
use Genesis\SQLExtension\Context\Interfaces\KeyStoreInterface;
use Genesis\SQLExtension\Context\Interfaces\SQLBuilderInterface;
use Genesis\SQLExtension\Context\Interfaces\SQLHistoryInterface;
use Genesis\SQLExtension\Tests\TestHelper;
use PDO;

/**
 * @group sqlContext
 * @group unit
 */
class APITest extends TestHelper
{
    /**
     * @var object The object to be tested.
     */
    private $testObject;

    /**
     * Sample connection string.
     */
    const CONNECTION_STRING = 'BEHAT_ENV_PARAMS=DBENGINE:mysql;DBSCHEMA:;DBNAME:abc;DBHOST:localhost;DBUSER:root;DBPASSWORD:toor;DBPREFIX:';

    public function setup()
    {
        // $_SESSION['behat']['GenesisSqlExtension']['notQuotableKeywords'] = [];

        putenv(self::CONNECTION_STRING);

        $this->dependencies['dbHelperMock'] = $this->getMockBuilder(DBManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getPrimaryKeyForTable')
            ->will($this->returnValue('id'));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getLastInsertId')
            ->will($this->returnValue(5));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getRequiredTableColumns')
            ->will($this->returnValue([]));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getLeftDelimiterForReservedWord')
            ->willReturn('`');

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getRightDelimiterForReservedWord')
            ->willReturn('`');

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getParams')
            ->will($this->returnValue(
                ['DBPREFIX' => 'dev_', 'DBNAME' => 'mydb', 'DBSCHEMA' => 'myschema']
            ));
        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getDatabaseProvider')
            ->will($this->returnValue(
                $this->createMock(DatabaseProviderInterface::class)
            ));

        $this->dependencies['sqlBuilder'] = $this->getMockBuilder(SQLBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dependencies['sqlBuilder']->expects($this->any())
            ->method('parseExternalQueryReferences')
            ->with($this->isType('string'))
            ->will($this->returnArgument(0));

        $this->dependencies['keyStoreMock'] = $this->getMockBuilder(KeyStoreInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dependencies['keyStoreMock']->expects($this->any())
            ->method('parseKeywordsInString')
            ->will($this->returnArgument(0));

        $this->dependencies['keyStoreMock']->expects($this->any())
            ->method('getKeywordIfExists')
            ->will($this->returnArgument(0));

        $this->dependencies['keyStoreMock']->expects($this->any())
            ->method('getKeyword')
            ->will($this->returnValue(5));

        $this->dependencies['sqlHistoryMock'] = $this->getMockBuilder(SQLHistoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->testObject = new API(
            $this->dependencies['dbHelperMock'],
            $this->dependencies['sqlBuilder'],
            $this->dependencies['keyStoreMock'],
            $this->dependencies['sqlHistoryMock']
        );
    }

    /**
     * Test that this method works with values provided.
     */
    public function testInsert()
    {
        $entity = 'database.unique1';
        $column = ['column1' => 'abc'];

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getRequiredTableColumns')
            ->with($this->isType('string'))
            ->will($this->returnValue([]));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('hasFetchedRows')
            ->will($this->onConsecutiveCalls(
                false,
                true,
                true
            ));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('execute')
            ->with($this->isType('string'))
            ->will($this->onConsecutiveCalls(
                $this->getPdoStatementWithRows(0),
                $this->getPdoStatementWithRows(1, [['id' => 237463]]),
                $this->getPdoStatementWithRows(1, [['id' => 237463]])
            ));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getFirstValueFromStatement')
            ->will($this->returnCallback(function ($statement) {
                return $statement->fetch(PDO::FETCH_BOTH);
            }));

        $convertedQuery1 = [
            'column1' => 'abc'
        ];

        $this->mockDependency(
            'sqlBuilder',
            'convertToArray',
            array('column1:abc'),
            $convertedQuery1
        );

        $this->mockDependency(
            'sqlBuilder',
            'constructSQLClause',
            array(
                'select',
                ' AND ',
                $convertedQuery1
            ),
            "column1 = 'abc'"
        );

        $this->mockDependency('dbHelperMock', 'getRequiredTableColumns', null, []);
        $this->mockDependency('sqlBuilder', 'quoteOrNot', null, "'abc'");
        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'unique1');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $result = $this->testObject->insert($entity, $column);

        // Expected SQL.
        $expectedSQL = "INSERT INTO dev_database.unique1 (`column1`) VALUES ('abc')";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
        // After execution select all values.
        $this->assertEquals('select', $this->testObject->getCommandType());
    }

    /**
     * Test that this method works with values provided.
     *
     * @group duplicate
     */
    public function testInsertDuplicateRecords()
    {
        $entity = 'database.unique1';
        $column = ['column1' => 'abc'];

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getRequiredTableColumns')
            ->with($this->isType('string'))
            ->will($this->returnValue([]));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('hasFetchedRows')
            ->will($this->onConsecutiveCalls(
                false,
                true,
                false
            ));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('execute')
            ->with($this->isType('string'))
            ->will($this->onConsecutiveCalls(
                $this->getPdoStatementWithRows(0),
                $this->getPdoStatementWithRows(1, [['id' => 237463]]),
                $this->getPdoStatementWithRows(1, [['id' => 237463]])
            ));

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getFirstValueFromStatement')
            ->will($this->returnCallback(function ($statement) {
                return $statement->fetch(PDO::FETCH_BOTH);
            }));

        $convertedQuery1 = [
            'column1' => 'abc'
        ];

        $this->mockDependency(
            'sqlBuilder',
            'convertToArray',
            array('column1:abc'),
            $convertedQuery1
        );

        $this->mockDependency(
            'sqlBuilder',
            'constructSQLClause',
            array(
                'select',
                ' AND ',
                $convertedQuery1
            ),
            "column1 = 'abc'"
        );

        $this->mockDependency('dbHelperMock', 'getRequiredTableColumns', null, []);
        $this->mockDependency('sqlBuilder', 'quoteOrNot', null, "'abc'");
        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'unique1');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $result = $this->testObject->insert($entity, $column);

        // Expected SQL.
        $expectedSQL = "INSERT INTO dev_database.unique1 (`column1`) VALUES ('abc')";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
        // After execution select all values.
        $this->assertEquals('select', $this->testObject->getCommandType());
    }

    /**
     * @expectedException Exception
     */
    public function testDelete()
    {
        $entity = '';
        $column = [];

        $this->testObject->delete($entity, $column);
    }

    /**
     * @expectedException Genesis\SQLExtension\Context\Exceptions\NoWhereClauseException
     */
    public function testDeleteNoWhereClauseException()
    {
        $entity = 'abc';
        $column = [];

        $this->testObject->delete($entity, $column);
    }

    /**
     * Test that this method works with values provided.
     */
    public function testDeleteWithWhere()
    {
        $entity = 'database.someTable';
        $column = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'NULL', 'column4' => 'what\'s up doc'];

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(),
                'hasFetchedRows' => true
            ]
        );

        $convertedQuery1 = [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'NULL',
            'column4' => 'what\'s up doc'
        ];

        $this->mockDependency(
            'sqlBuilder',
            'convertToArray',
            array('column1:abc,column2:xyz,column3:NULL,column4:what\'s up doc'),
            $convertedQuery1
        );

        $this->mockDependency(
            'sqlBuilder',
            'constructSQLClause',
            array(
                'delete',
                ' AND ',
                $convertedQuery1
            ),
            "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\\'s up doc'"
        );

        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $result = $this->testObject->delete($entity, $column);

        // Expected SQL.
        $expectedSQL = "DELETE FROM dev_database.someTable WHERE `column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\'s up doc'";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
        $this->assertEquals('delete', $this->testObject->getCommandType());
    }

    /**
     * @expectedException Exception
     */
    public function testUpdateNoTable()
    {
        $entity = '';
        $with = [];
        $columns = [];

        $this->testObject->update($entity, $with, $columns);
    }

    /**
     * @expectedException Genesis\SQLExtension\Context\Exceptions\NoWhereClauseException
     */
    public function testUpdateNoWhereOrColumns()
    {
        $entity = 'abc';
        $with = [];
        $columns = [];

        $this->testObject->update($entity, $with, $columns);
    }

    /**
     * Test that this method works with values provided.
     */
    public function testUpdateWithValues()
    {
        $entity = 'database.someTable2';
        $with = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'NULL', 'column4' => 'what\'s up doc'];
        $columns = ['id' => '134', 'photo' => '!NULL', 'column' => 'what\'s up doc'];

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(1, [
                    ['email' => 'its.inevitable@hotmail.com', 'name' => 'Abdul']
                ]),
                'hasFetchedRows' => true
            ]
        );

        $convertedQuery1 = [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'NULL',
            'column4' => 'what\'s up doc'
        ];

        $convertedQuery2 = [
            'id' => '134',
            'photo' => '!NULL',
            'column' => 'what\'s up doc'
        ];

        $this->mockDependencyValueMap('sqlBuilder', 'convertToArray', array(
                array('column1:abc,column2:xyz,column3:NULL,column4:what\'s up doc', $convertedQuery1),
                array('id:134,photo:!NULL,column:what\'s up doc', $convertedQuery2)
            ));

        $this->mockDependencyValueMap('sqlBuilder', 'constructSQLClause', array(
                array('update', ', ', $convertedQuery1, "`column1` = 'abc', `column2` = 'xyz', `column3` = NULL, `column4` = 'what\'s up doc'"),
                array('update', ' AND ', $convertedQuery2, "`id` = 134 AND `photo` is not NULL AND `column` = 'what\'s up doc'")
            ));

        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable2');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getFirstValueFromStatement')
            ->will($this->returnCallback(function ($statement) {
                return $statement->fetch(PDO::FETCH_BOTH);
            }));

        $result = $this->testObject->update($entity, $with, $columns);

        // Expected SQL.
        $expectedSQL = "UPDATE dev_database.someTable2 SET `column1` = 'abc', `column2` = 'xyz', `column3` = NULL, `column4` = 'what\'s up doc' WHERE `id` = 134 AND `photo` is not NULL AND `column` = 'what\'s up doc'";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
        // After execution select all values.
        $this->assertEquals('select', $this->testObject->getCommandType());
    }

    /**
     * Test that this method works as expected.
     */
    public function testSelect()
    {
        $entity = 'database.someTable2';
        $where = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'NULL', 'column4' => 'what\'s up doc'];

        $convertedQuery1 = [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'NULL',
            'column4' => 'what\'s up doc'
        ];

        $this->mockDependency(
            'sqlBuilder',
            'convertToArray',
            array('column1:abc,column2:xyz,column3:NULL,column4:what\'s up doc'),
            $convertedQuery1
        );

        $this->mockDependency(
            'sqlBuilder',
            'constructSQLClause',
            array(
                'select',
                ' AND ',
                $convertedQuery1
            ),
            "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\\'s up doc'"
        );

        $expectedResult = [['id' => 5, 'name' => 'Abdul']];
        $statement = $this->getPdoStatementWithRows();
        $statement->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue($expectedResult[0]));

        $this->mockDependency('dbHelperMock', 'execute', ["SELECT * FROM dev_database.someTable2 WHERE `column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\'s up doc'"], $statement);

        $this->mockDependency('dbHelperMock', 'throwErrorIfNoRowsAffected', [$statement]);
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');
        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable2');

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getFirstValueFromStatement')
            ->will($this->returnCallback(function ($statement) {
                return $statement->fetch(PDO::FETCH_BOTH);
            }));

        $result = $this->testObject->select($entity, $where);

        $this->assertEquals($expectedResult[0], $result);
    }

    /**
     * Test that this method works with values provided.
     */
    public function testSelectWithOtherValues()
    {
        $entity = 'database.someTable4';
        $with = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => '!NULL'];

        $this->mockDependency(
            'sqlBuilder',
            'convertTableNodeToSingleContextClause',
            [$with],
            'column1:abc,column2:xyz,column3:!NULL'
        );

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(),
                'hasFetchedRows' => true
            ]
        );

        $this->mockDependency('sqlBuilder', 'convertToArray', ['column1:abc,column2:xyz,column3:!NULL'], [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => '!NULL'
        ]);

        $this->mockDependency('sqlBuilder', 'constructSQLClause', null, "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` is not NULL");

        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable4');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $result = $this->testObject->assertExists($entity, $with);

        // Expected SQL.
        $expectedSQL = "SELECT * FROM dev_database.someTable4 WHERE `column1` = 'abc' AND `column2` = 'xyz' AND `column3` is not NULL";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
        $this->assertEquals('select', $this->testObject->getCommandType());
    }

    /**
     * Test that this method works with values provided.
     *
     * @expectedException Genesis\SQLExtension\Context\Exceptions\SelectException
     */
    public function testSelectThrowsException()
    {
        $entity = 'database.someTable4';
        $with = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => '!NULL'];

        $this->mockDependency(
            'sqlBuilder',
            'convertTableNodeToSingleContextClause',
            [$with],
            'column1:abc,column2:xyz,column3:!NULL'
        );

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(false),
                'hasFetchedRows' => false
            ]
        );

        $this->mockDependency('sqlBuilder', 'convertToArray', ['column1:abc,column2:xyz,column3:!NULL'], [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => '!NULL'
        ]);

        $this->mockDependency('sqlBuilder', 'constructSQLClause', null, "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` is not NULL");

        $this->testObject->select($entity, $with);
    }

    /**
     * Test that this method works with values provided.
     *
     * @expectedException Exception
     */
    public function testAssertNotExistsException()
    {
        $entity = 'database.someTable3';
        $with = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'what\'s up doc'];
        $withString = 'column1:abc,column2:xyz,column3:what\'s up doc';

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(),
                'hasFetchedRows' => true
            ]
        );

        $this->mockDependency('sqlBuilder', 'convertToArray', [$withString], [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'what\'s up doc'
        ]);

        $this->mockDependency('sqlBuilder', 'constructSQLClause', null, "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` = 'what\'s up doc'");

        $this->testObject->assertNotExists($entity, $with);
    }

    /**
     * Test that this method works with values provided.
     */
    public function testAssertNotExists()
    {
        $entity = 'database.someTable3';
        $with = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'what\'s up doc'];
        $withString = 'column1:abc,column2:xyz,column3:what\'s up doc';

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(),
                'hasFetchedRows' => false
            ]
        );

        $this->mockDependency('sqlBuilder', 'convertToArray', [$withString], [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'what\'s up doc'
        ]);

        $this->mockDependency('sqlBuilder', 'constructSQLClause', null, "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` = 'what\'s up doc'");

        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable3');
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');

        $result = $this->testObject->assertNotExists($entity, $with);

        $expectedSQL = "SELECT * FROM dev_database.someTable3 WHERE `column1` = 'abc' AND `column2` = 'xyz' AND `column3` = 'what\'s up doc'";

        $this->assertEquals($expectedSQL, $result);
    }

    /**
     * Test that this method works with values containing wildcards for a LIKE search.
     */
    public function testAssertExists()
    {
        $entity = 'database.someTable4';
        $with = ['column1' => 'abc', 'column2' => '%xyz%'];

        $this->mockDependency(
            'sqlBuilder',
            'convertTableNodeToSingleContextClause',
            [$with],
            'column1:abc,column2:%xyz%'
        );

        $this->mockDependencyMethods(
            'dbHelperMock',
            [
                'execute' => $this->getPdoStatementWithRows(),
                'hasFetchedRows' => true
            ]
        );

        $this->mockDependency('sqlBuilder', 'convertToArray', ['column1:abc,column2:%xyz%'], [
            'column1' => 'abc',
            'column2' => '%xyz%'
        ]);

        $this->mockDependency('sqlBuilder', 'constructSQLClause', null, "`column1` = 'abc' AND `column2` LIKE '%xyz%'");
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');
        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable4');

        $result = $this->testObject->assertExists($entity, $with);

        // Expected SQL.
        $expectedSQL = "SELECT * FROM dev_database.someTable4 WHERE `column1` = 'abc' AND `column2` LIKE '%xyz%'";

        // Assert.
        $this->assertEquals($expectedSQL, $result);
        $this->assertNotNull($this->testObject->getEntity());
    }

    public function testCount()
    {
        $entity = 'database.someTable2';
        $where = ['column1' => 'abc', 'column2' => 'xyz', 'column3' => 'NULL', 'column4' => 'what\'s up doc'];

        $convertedQuery1 = [
            'column1' => 'abc',
            'column2' => 'xyz',
            'column3' => 'NULL',
            'column4' => 'what\'s up doc'
        ];

        $this->mockDependency(
            'sqlBuilder',
            'convertToArray',
            array('column1:abc,column2:xyz,column3:NULL,column4:what\'s up doc'),
            $convertedQuery1
        );

        $this->mockDependency(
            'sqlBuilder',
            'constructSQLClause',
            array(
                'select',
                ' AND ',
                $convertedQuery1
            ),
            "`column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\\'s up doc'"
        );

        $expectedResult = [['SELECT_COUNT_someTable2' => 5, 0 => 5]];
        $statement = $this->getPdoStatementWithRows();
        $statement->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue($expectedResult[0]));

        $this->mockDependency('dbHelperMock', 'execute', ["SELECT COUNT(*) AS SELECT_COUNT_someTable2 FROM dev_database.someTable2 WHERE `column1` = 'abc' AND `column2` = 'xyz' AND `column3` is NULL AND `column4` = 'what\'s up doc'"], $statement);

        $this->mockDependency('dbHelperMock', 'throwErrorIfNoRowsAffected', [$statement]);
        $this->mockDependency('sqlBuilder', 'getSearchConditionOperatorForColumns', null, ' AND ');
        $this->mockDependency('sqlBuilder', 'getPrefixedDatabaseName', null, 'dev_database');
        $this->mockDependency('sqlBuilder', 'getTableName', null, 'someTable2');

        $this->dependencies['dbHelperMock']->expects($this->any())
            ->method('getFirstValueFromStatement')
            ->will($this->returnCallback(function ($statement) {
                return $statement->fetch(PDO::FETCH_BOTH);
            }));

        $result = $this->testObject->count($entity, $where);

        $this->assertInternalType('int', $result);
        $this->assertSame($result, 5);
    }
}
