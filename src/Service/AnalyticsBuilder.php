<?php
namespace App\Service\Analytics;

use SomeClasses;

/**
 * Сборщик данных аналитики из внутренней БД
 */
class AnalyticsBuilder
{
   //выпилено всё, кроме методов

    /**
     * Внутренняя статистика
     */
    public function buildInner()
    {
        $statuses   = $this->getStatuses();
        $statusMap  = $this->getActionStatusMap();
        $tableInner = new AnalyticsTable;
        $tableInner->setHeader([
            'date'        => 'Дата',
            'client'      => 'Новые регистрации',
            'aclient'     => 'Выполнено ЦД среди новых регистраций',
        ]);
        foreach ($statuses as $statusId => $statusName) {
            $tableInner->addHeaderCell((string) $statusId, $statusName);
        }
        $tableInner->addHeaderCell('allStatuses', 'Общее количество целевых действий');
        $tableInner->addHeaderCell('allAclient', 'Общее количество активных пользователей');
        $innerResults = [];

        $period = new \DatePeriod(
            new \DateTime($this->dateStart),
            new \DateInterval('P1D'),
            new \DateTime($this->dateEnd)
        );
        foreach ($period as $key => $value) {
            $innerResults[$value->getTimestamp()] = [
                'date'        => $value->format('d/m/Y'),
                'client'      => 0,
                'aclient'     => 0,
                'allStatuses' => 0,
                'allAclient'  => 0,
            ];
            foreach ($statuses as $statusId => $statusName) {
                $innerResults[$value->getTimestamp()][$statusId] = 0;
            }
        }

        $registered = $this->retrieveRegisteredUsers();
        $active     = $this->retrieveActiveUsers();

        $nestedQb = new SqlQueryBuilder;
        $mainQb   = new SqlQueryBuilder;

        // receipts by status (график Статусы целевых действий)
        // статистика по клиенту
        if ($this->client && !$this->action) {
            $actionsCollection = $this->client->getActions();
            $actions = [];
            $nestedQbArr = [];

            // если выбрана механика, то берём акции этой механики
            // если НЕ выбрана механика, то будет перебор всех акций клиента
            if ($this->promoType) {
                foreach ($actionsCollection as $a) {
                    if ($a->getPromoType() === $this->promoType) {
                        array_push($actions, $a);
                    }
                }
            } else {
                $actions = $actionsCollection;
            }

            foreach ($actions as $key => $action) {
                $nestedQb = new SqlQueryBuilder;
                $alias = 't' . $key;
                $actionId[$key] = $action->getId();

                if ($this->promoType) {
                    $actionPromoType = $this->promoType;
                } else {
                    $actionPromoType = $action->getPromoType();
                }
                
                switch ($actionPromoType) {
                    case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                        if ($this->promoType) {
                            $status = "`$alias`.`moderate` AS 'status'";
                        } else {
                            $status = "CASE
                                        WHEN `$alias`.`moderate` = 1 THEN 0
                                        WHEN `$alias`.`moderate` = 2 THEN 1
                                        WHEN `$alias`.`moderate` >= 3 THEN 2
                                       END AS 'status'"
                            ;
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT FROM_UNIXTIME(`$alias`.`created_at`, '%Y-%m-%d') AS 'day',
                                              $status
                                              FROM `table1` `$alias` :JOIN :WHERE")
                        ;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                        if ($this->promoType) {
                            $status = "`$alias`.`status_inner` AS 'status'";
                        } else {
                            $status = "IF(`$alias`.`status_inner` >= 2, 2, `$alias`.`status_inner`) AS 'status'";
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT FROM_UNIXTIME(`$alias`.`created_at`, '%Y-%m-%d') AS 'day',
                                              $status
                                              FROM `table2` `$alias` :JOIN :WHERE")
                        ;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                        if ($this->promoType) {
                            $status = "`$alias`.`status` AS 'status'";
                        } else {
                            $status = "CASE
                                        WHEN `$alias`.`status` = 1 THEN 0
                                        WHEN `$alias`.`status` = 2 THEN 2
                                        WHEN `$alias`.`status` = 3 THEN 1
                                        WHEN `$alias`.`status` = 4 THEN 2
                                       END AS 'status'"
                            ;
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT FROM_UNIXTIME(`$alias`.`created_at`, '%Y-%m-%d') AS 'day',
                                              $status
                                              FROM `table3` `$alias` :JOIN :WHERE")
                        ;
                        break;
                }

                $nestedQb
                    ->addJoinIf("LEFT JOIN `table4` a ON `$alias`.action_id=table4_id", [
                        'promoType' => $this->promoType
                    ])
                    ->addAndWhere("`$alias`.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                    ->setParameter("dateStart", $this->dateStart)
                    ->addAndWhere("`$alias`.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                    ->setParameter("dateEnd", $this->dateEnd)
                    ->addAndWhereIf("`$alias`.`action_id` = :actionId" . $key, ["actionId" . $key => $actionId[$key]])
                    ->addAndWhereIf("t4.`promo_type` = :promoType", ["promoType" => $this->promoType])
                ;
                array_push($nestedQbArr, $nestedQb);
            }

            $strFrom = '';
    
            for ($i = 0; $i < count($nestedQbArr); $i++) {
                if ($i === (count($nestedQbArr) - 1)) {
                    $strFrom .= ':nested' . $i;
                    $mainQb
                        ->setOriginQuery("SELECT COUNT(*) AS 'count', agg.day, agg.status
                                        FROM ({$strFrom}) agg
                                        GROUP BY agg.day, agg.status")
                    ;
                } else {
                    $strFrom .= ':nested' . $i . ' UNION ALL ';
                }

                $mainQb
                    ->addNestedQuery('nested' . $i, $nestedQbArr[$i])
                ;
            }
        }

        
        
        // узконаправленная статистика по фильтрам
        else if ($this->client && $this->action || $this->action || !$this->client && $this->promoType) {
            if ($this->action) {
                $actionPromoType = $this->action->getPromoType();
            } else {
                $actionPromoType = $this->promoType;
            }

            switch ($actionPromoType) {
                case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                    $targetTable  = 'table1';
                    $statusColumn = 'moderate';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                    $targetTable  = 'table3';
                    $statusColumn = 'status';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                    $targetTable  = 'table2';
                    $statusColumn = 'status_inner';
                    break;
            }

            $nestedQb
                ->setOriginQuery("SELECT 
                                  FROM_UNIXTIME(t.`created_at`, '%Y-%m-%d') AS 'day',
                                  t.`$statusColumn` AS 'status'
                                  FROM `$targetTable` t :JOIN")
                ->addJoinIf('LEFT JOIN `table4` ON t.action_id=table4_id', [
                    'promoType' => $this->promoType
                ])
                ->addAndWhere('t.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('t.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
                ->addAndWhereIf('t.`action_id` = :actionId', ['actionId' => $this->actionId])
                ->addAndWhereIf('action.`promo_type` = :promoType', ['promoType' => $this->promoType])
            ;

            $mainQb
                ->setOriginQuery('SELECT COUNT(*) AS count, agg.day, agg.status FROM (:nested) agg GROUP BY agg.day, agg.status')
                ->addNestedQuery('nested', $nestedQb)
            ;
        }

        // общая статистика без выбора фильтра по клиенту, акции, механике
        else {
            $nestedQb1 = new SqlQueryBuilder;
            $nestedQb2 = new SqlQueryBuilder;
            $nestedQb3 = new SqlQueryBuilder;

            $nestedQb1
                ->setOriginQuery('SELECT FROM_UNIXTIME(aci.`created_at`, "%Y-%m-%d") AS "day",
                                  CASE
                                    WHEN aci.`moderate` = 1 THEN 0
                                    WHEN aci.`moderate` = 2 THEN 1
                                    WHEN aci.`moderate` >= 3 THEN 2
                                  END AS "status"
                                  FROM `table1` aci')
                ->addAndWhere('aci.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('aci.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb2
                ->setOriginQuery('SELECT FROM_UNIXTIME(r.`created_at`, "%Y-%m-%d") AS "day",
                                  IF(r.`status_inner` >= 2, 2, r.`status_inner`) AS "status"
                                  FROM `table2` r')
                ->addAndWhere('r.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb3
                ->setOriginQuery('SELECT FROM_UNIXTIME(up.`created_at`, "%Y-%m-%d") AS "day",
                                  CASE
                                    WHEN up.`status` = 1 THEN 0
                                    WHEN up.`status` = 2 THEN 2
                                    WHEN up.`status` = 3 THEN 1
                                    WHEN up.`status` = 4 THEN 2
                                  END AS "status"
                                  FROM `table3` up')
                ->addAndWhere('up.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('up.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            
            $mainQb
                ->setOriginQuery('SELECT COUNT(*) AS "count", agg.day, agg.status
                                  FROM (:nested1 UNION ALL :nested2 UNION ALL :nested3) agg
                                  GROUP BY agg.day, agg.status') 
                ->addNestedQuery('nested1', $nestedQb1)
                ->addNestedQuery('nested2', $nestedQb2)
                ->addNestedQuery('nested3', $nestedQb3)
            ;
        }
        
        $receipts = $mainQb->buildQuery($this->manager, [
            'count'  => 'integer', 
            'day'    => 'string',
            'status' => 'integer',
        ])->getResult();

        //post-processing
        foreach ($registered as $row) {
            $key = (new \DateTime($row['day']))->getTimestamp();
            $innerResults[$key]['client'] = $row['count'];
        }
        foreach ($active as $row) {
            $key = (new \DateTime($row['day']))->getTimestamp();
            $innerResults[$key]['aclient'] = $row['count'];
        }
        foreach ($receipts as $row) {
            $key = (new \DateTime($row['day']))->getTimestamp();
            $status = $statusMap[$row['status']];
            if (!isset($innerResults[$key][$status])) {
                $innerResults[$key][$status] = 0;
            }
            $innerResults[$key][$status] += $row['count'];
        }
        foreach ($innerResults as $k => $row) {
            $innerResults[$k]['allStatuses'] = array_reduce(
                array_keys($statuses), function($carry, $item) use ($row) {
                    return $carry + ($row[$item]??0);
                }, 0
            );
        }

        $this->loadAllActiveClients($innerResults);

        return $tableInner->setBody($innerResults);
    }

    /**
     * Сводная внутренняя статистика
     *
     * @return \App\Service\Analytics\Dto\AnalyticsTable
     */
    public function buildSummary()
    {
        $statuses  = $this->getStatuses();
        $statusMap = $this->getActionStatusMap();

        $tableSummary   = new AnalyticsTable;
        $tableSummary->setHeader([
            'name'    => 'Статус',
            'count'   => 'Количество (% от общего количества ЦД)',
        ]);

        $summaryResults = [];
        $summaryTotal   = 0;

        $mainQb   = new SqlQueryBuilder;

        // summary total (раздел Сводная внутренняя статистика по ЦД - Всего)
         // статистика по клиенту
         if ($this->client && !$this->action) {
            $actionsCollection = $this->client->getActions();
            $actions = [];
            $nestedQbArr = [];

            // если выбрана механика, то берём акции этой механики
            // если НЕ выбрана механика, то будет перебор всех акций клиента
            if ($this->promoType) {
                foreach ($actionsCollection as $a) {
                    if ($a->getPromoType() === $this->promoType) {
                        array_push($actions, $a);
                    }
                }
            } else {
                $actions = $actionsCollection;
            }

            foreach ($actions as $key => $action) {
                $nestedQb = new SqlQueryBuilder;
                $actionId[$key] = $action->getId();

                if ($this->promoType) {
                    $actionPromoType[$key] = $this->promoType;
                } else {
                    $actionPromoType[$key] = $action->getPromoType();
                }
                
                switch ($actionPromoType[$key]) {
                    case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                        $targetTable = 'table1';
                        $alias = $targetTable . $key;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                        $targetTable = 'table3';
                        $alias = $targetTable . $key;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                        $targetTable = 'table2';
                        $alias = $targetTable . $key;
                        break;
                }

                $nestedQb
                    ->setOriginQuery("SELECT COUNT(*) AS 'count' FROM `$targetTable` `$alias` :JOIN")
                    ->addJoinIf("LEFT JOIN `table4` t4 ON `$alias`.action_id=table4_id", [
                        'promoType' => $actionPromoType[$key]
                    ])
                    ->addAndWhere("`$alias`.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                    ->setParameter("dateStart", $this->dateStart)
                    ->addAndWhere("`$alias`.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                    ->setParameter("dateEnd", $this->dateEnd)
                    ->addAndWhereIf("`$alias`.`action_id` = :actionId" . $key, ["actionId" . $key => $actionId[$key]])
                    ->addAndWhereIf("t4.`promo_type` = :promoType" . $key, ["promoType" . $key => $actionPromoType[$key]])
                ;
                array_push($nestedQbArr, $nestedQb);
            }

            $strFrom = '';
    
            for ($i = 0; $i < count($nestedQbArr); $i++) {
                if ($i === (count($nestedQbArr) - 1)) {
                    $strFrom .= '(:nested' . $i . ')';
                    $mainQb
                        ->setOriginQuery("SELECT {$strFrom} as 'count'")
                    ;
                } else {
                    $strFrom .= '(:nested' . $i . ')' . ' + ';
                }

                $mainQb
                    ->addNestedQuery('nested' . $i, $nestedQbArr[$i])
                ;
            }

            $summaryTotal = $mainQb
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ;
        }

        // узконаправленная статистика по фильтрам
        else if ($this->client && $this->action || $this->action || !$this->client && $this->promoType) {
            if ($this->action) {
                $actionPromoType = $this->action->getPromoType();
            } else {
                $actionPromoType = $this->promoType;
            }

            switch ($actionPromoType) {
                case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                    $targetTable = 'table1';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                    $targetTable = 'table3';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                    $targetTable = 'table2';
                    break;
            }
            
            $summaryTotal = $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count' FROM `$targetTable` t :JOIN")
                ->addJoinIf('LEFT JOIN `table4` t4 ON t.action_id=table4_id', [
                    'promoType' => $this->promoType
                ])
                ->addAndWhere('t.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('t.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
                ->addAndWhereIf('t.`action_id` = :actionId', ['actionId' => $this->actionId])
                ->addAndWhereIf('t4.`promo_type` = :promoType', ['promoType' => $this->promoType])
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ;
        }

        // общая статистика без выбора фильтра по клиенту, акции, механике
        else {
            $nestedQb1 = new SqlQueryBuilder;
            $nestedQb2 = new SqlQueryBuilder;
            $nestedQb3 = new SqlQueryBuilder;

            $nestedQb1
                ->setOriginQuery('SELECT COUNT(*) FROM `table1` aci')
                ->addAndWhere('aci.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('aci.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb2
                ->setOriginQuery('SELECT COUNT(*) FROM `table2` r')
                ->addAndWhere('r.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb3
                ->setOriginQuery('SELECT COUNT(*) FROM `table3` up')
                ->addAndWhere('up.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('up.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            
            $mainQb
                ->setOriginQuery('SELECT (:nested1) + (:nested2) + (:nested3) as "count"')
                ->addNestedQuery('nested1', $nestedQb1)
                ->addNestedQuery('nested2', $nestedQb2)
                ->addNestedQuery('nested3', $nestedQb3)
            ;

            $summaryTotal = $mainQb
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ;
        }
        
        $mainQb->reset();

        // summary by status (раздел Сводная внутренняя статистика по ЦД - статусы)
        // статистика по клиенту
        if ($this->client && !$this->action) {
            $actionsCollection = $this->client->getActions();
            $actions = [];
            $nestedQbArr = [];

            // если выбрана механика, то берём акции этой механики
            // если НЕ выбрана механика, то будет перебор всех акций клиента
            if ($this->promoType) {
                foreach ($actionsCollection as $a) {
                    if ($a->getPromoType() === $this->promoType) {
                        array_push($actions, $a);
                    }
                }
            } else {
                $actions = $actionsCollection;
            }

            foreach ($actions as $key => $action) {
                $nestedQb = new SqlQueryBuilder;
                $alias = 't' . $key;
                $actionId[$key] = $action->getId();

                if ($this->promoType) {
                    $actionPromoType = $this->promoType;
                } else {
                    $actionPromoType = $action->getPromoType();
                }
                
                switch ($actionPromoType) {
                    case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                        if ($this->promoType) {
                            $status = "`$alias`.`moderate` AS 'status'";
                        } else {
                            $status = "CASE
                                        WHEN `$alias`.`moderate` = 1 THEN 0
                                        WHEN `$alias`.`moderate` = 2 THEN 1
                                        WHEN `$alias`.`moderate` >= 3 THEN 2
                                       END AS 'status'"
                            ;
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                              $status
                                              FROM `table1` `$alias` :JOIN :WHERE
                                              GROUP BY `status`")
                        ;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                    case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                        if ($this->promoType) {
                            $status = "`$alias`.`status_inner` AS 'status'";
                        } else {
                            $status = "IF(`$alias`.`status_inner` >= 2, 2, `$alias`.`status_inner`) AS 'status'";
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                              $status
                                              FROM `table2` `$alias` :JOIN :WHERE
                                              GROUP BY `status`")
                        ;
                        break;
                    case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                    case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                        if ($this->promoType) {
                            $status = "`$alias`.`status` AS 'status'";
                        } else {
                            $status = "CASE
                                        WHEN `$alias`.`status` = 1 THEN 0
                                        WHEN `$alias`.`status` = 2 THEN 2
                                        WHEN `$alias`.`status` = 3 THEN 1
                                        WHEN `$alias`.`status` = 4 THEN 2
                                       END AS 'status'"
                            ;
                        }
                        $nestedQb
                            ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                              $status
                                              FROM `table3` `$alias` :JOIN :WHERE
                                              GROUP BY `status`")
                        ;
                        break;
                }

                $nestedQb
                    ->addJoinIf("LEFT JOIN `table4` t4 ON `$alias`.action_id=table4_id", [
                        'promoType' => $this->promoType
                    ])
                    ->addAndWhere("`$alias`.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                    ->setParameter("dateStart", $this->dateStart)
                    ->addAndWhere("`$alias`.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                    ->setParameter("dateEnd", $this->dateEnd)
                    ->addAndWhereIf("`$alias`.`action_id` = :actionId" . $key, ["actionId" . $key => $actionId[$key]])
                    ->addAndWhereIf("t4.`promo_type` = :promoType", ["promoType" => $this->promoType])
                ;
                array_push($nestedQbArr, $nestedQb);
            }

            $strFrom = '';
    
            for ($i = 0; $i < count($nestedQbArr); $i++) {
                if ($i === (count($nestedQbArr) - 1)) {
                    $strFrom .= ':nested' . $i;
                    $mainQb
                        ->setOriginQuery("SELECT SUM(agg.count) AS 'count', agg.status
                                        FROM ({$strFrom}) agg
                                        GROUP BY agg.status")
                    ;
                } else {
                    $strFrom .= ':nested' . $i . ' UNION ALL ';
                }

                $mainQb
                    ->addNestedQuery('nested' . $i, $nestedQbArr[$i])
                ;
            }

            $summaryByStatus = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                ])
                ->getResult()
            ;
        }

        // узконаправленная статистика по фильтрам
        else if ($this->client && $this->action || $this->action || !$this->client && $this->promoType) {
            if ($this->action) {
                $actionPromoType = $this->action->getPromoType();
            } else {
                $actionPromoType = $this->promoType;
            }

            switch ($actionPromoType) {
                case ActionPromotypeRegistry::PROMOTYPE_CREATIVE:
                    $targetTable  = 'table1';
                    $statusColumn = 'moderate';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_UNIQ_POMOCODE:
                case ActionPromotypeRegistry::PROMOTYPE_NONUNIQ_POMOCODE:
                    $targetTable  = 'table3';
                    $statusColumn = 'status';
                    break;
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_REVIEW:
                case ActionPromotypeRegistry::PROMOTYPE_QRCODE_AND_VOTE:
                    $targetTable  = 'table2';
                    $statusColumn = 'status_inner';
                    break;
            }

            $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                  t.`$statusColumn` AS 'status'
                                  FROM `$targetTable` t :JOIN :WHERE
                                  GROUP BY `status`")
                ->addJoinIf('LEFT JOIN `table4` t4 ON t.action_id=table4_id', [
                    'promoType' => $this->promoType
                ])
                ->addAndWhere('t.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('t.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
                ->addAndWhereIf('t.`action_id` = :actionId', ['actionId' => $this->actionId])
                ->addAndWhereIf('t4.`promo_type` = :promoType', ['promoType' => $this->promoType])
            ;

            $summaryByStatus = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                ])
                ->getResult()
            ;
        }

        // общая статистика без выбора фильтра по клиенту, акции, механике
        else {
            $nestedQb1 = new SqlQueryBuilder;
            $nestedQb2 = new SqlQueryBuilder;
            $nestedQb3 = new SqlQueryBuilder;

            $nestedQb1
                ->setOriginQuery('SELECT COUNT(*) AS "count", 
                                  CASE
                                    WHEN aci.`moderate` = 1 THEN 0
                                    WHEN aci.`moderate` = 2 THEN 1
                                    WHEN aci.`moderate` >= 3 THEN 2
                                  END AS "status"
                                  FROM `table1` aci :WHERE
                                  GROUP BY `status`')
                ->addAndWhere('aci.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('aci.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb2
                ->setOriginQuery('SELECT COUNT(*) AS "count",
                                  IF(r.`status_inner` >= 2, 2, r.`status_inner`) AS "status"
                                  FROM `table2` r :WHERE
                                  GROUP BY `status`')
                ->addAndWhere('r.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            $nestedQb3
                ->setOriginQuery('SELECT COUNT(*) AS "count",
                                  CASE
                                    WHEN up.`status` = 1 THEN 0
                                    WHEN up.`status` = 2 THEN 2
                                    WHEN up.`status` = 3 THEN 1
                                    WHEN up.`status` = 4 THEN 2
                                  END AS "status"
                                  FROM `table3` up :WHERE
                                  GROUP BY `status`')
                ->addAndWhere('up.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('up.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
            ;
            
            $mainQb
                ->setOriginQuery('SELECT SUM(agg.count) AS "count", agg.status
                                  FROM (:nested1 UNION ALL :nested2 UNION ALL :nested3) agg
                                  GROUP BY agg.status') 
                ->addNestedQuery('nested1', $nestedQb1)
                ->addNestedQuery('nested2', $nestedQb2)
                ->addNestedQuery('nested3', $nestedQb3)
            ;

            $summaryByStatus = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                ])
                ->getResult()
            ;
        }

        //post-processing
        foreach ($statuses as $statusId => $statusName) {
            $summaryResults[$statusId] = [
                'name'    => $statusName,
                'count'   => 0,
            ];
        }
        foreach ($summaryByStatus as $row) {
            $key = $statusMap[$row['status']];
            $summaryResults[$key]['count'] += $row['count'];
        }
        
        if ($summaryTotal) {
            foreach ($summaryResults as $key => $row) {
                $summaryResults[$key]['count1'] = $row['count'] . 
                        ' (' . round(100 * $row['count']/$summaryTotal, 2) . '%)';
            }
        }
        $summaryResults[] = [
            'name'   => 'Всего',
            'count'  => $summaryTotal,
            'count1' => $summaryTotal,
        ];

        $tableSummary->setBody($summaryResults);
        return $tableSummary->setFooter([
            'name'    => 'Всего',
            'count'   => $summaryTotal,
        ]);
    }

    /**
     * Возврат ЦА
     */
    public function buildReturnTargetAudience()
    {
        $receiptRepo = $this->manager->getRepository(table2::class);
        // таблица с фильтрами в разрезе дат
        $result   = [];
        $receipts = [];
        $dayFormat = 'd/m/Y';

        $period = new \DatePeriod(
            new \DateTime($this->dateStart),
            new \DateInterval('P1D'),
            new \DateTime($this->dateEnd)
        );
        foreach ($period as $key => $value) {
            $result[$value->format($dayFormat)] = [
                'date'               => $value->format($dayFormat), 
                'quantityUsers'      => 0, 
                'permanentUsers'     => 0,
                'percent'            => 0,
            ];
        }

        // фильтр по акции
        if (!empty($this->actionId)) {
            $dateStartTs = strtotime($this->dateStart);
            $dateEndTs   = strtotime($this->dateEnd);

            $receiptsQB = $receiptRepo->createQueryBuilder('r')
                ->andWhere('r.createdAt >= :dateStart')->setParameter('dateStart', $dateStartTs)
                ->andWhere('r.createdAt <= :dateEnd')->setParameter('dateEnd', $dateEndTs)
                ->andWhere('r.actionId = :actionId')->setParameter('actionId', $this->actionId)
            ;
            
        }   

        // фильтр по бренду
        if (!empty($this->brandId)) {
            $receiptsQB = $receiptRepo->createQueryBuilder('r')
                ->join('r.table4', 't4')
                ->andWhere('a.dateStart <= :dateStart')->setParameter('dateStart', $this->dateStart)
                ->andWhere('a.dateEnd >= :dateEnd')->setParameter('dateEnd', $this->dateEnd)
                ->andWhere('a.brandId = :brandId')->setParameter('brandId', $this->brandId)
            ;
        }

        //постпроцессинг
        if (isset($receiptsQB)) {
            $receipts = $receiptsQB->getQuery()->getResult();
            foreach ($receipts as $table2) {
                $dateKey = date($dayFormat, $table2->getCreatedAt());
                if (!isset($result[$dateKey])) {
                    continue;
                    //могут приходить чеки, которые были загружены за временными рамками акции
                }
                $userId = $table2->getUser()->getId();
                if (!isset($result[$dateKey]['usersId'])) {
                    $result[$dateKey]['usersId'] = [];
                }
                /** @var array $result */ //то IDE ругается
                if (in_array($userId, $result[$dateKey]['usersId'])) {
                    continue;
                }
    
                // id участников текущей акции/бренда
                $result[$dateKey]['usersId'][] = $userId;
    
                // уникальное количество юзеров акции/бренда в этот день
                $result[$dateKey]['quantityUsers'] = count($result[$dateKey]['usersId']);
    
                // находим участников и в другой акции
                $userActions = $table2->getUser()->getAction();
                foreach ($userActions as $uAction) {
                    if ($table2->getAction()->getId() !== $uAction->getId()) {
                        $result[$dateKey]['permanentUsers']++;
                        break;
                    }
                }
    
                if ($result[$dateKey]['quantityUsers'] !== 0) {
                    $result[$dateKey]['percent'] = round(($result[$dateKey]['permanentUsers'] / $result[$dateKey]['quantityUsers']) * 100) . '%';
                } else {
                    $result[$dateKey]['percent'] = 0;
                }
            }
        }
        
        $targetAudience = new AnalyticsTable;
        $targetAudience->setHeader([
            'date'           => 'Дата',
            'quantityUsers'  => 'Количество участников',
            'permanentUsers' => 'Кол.-во участников в двух и более акциях (включая выбранную)',
            'percent'        => 'Процент',
        ]);
        $targetAudience->setBody($result);
       
        return $targetAudience;
    }

    /**
     * Торговые сети
     */
    public function buildTrader()
    {
        $traders = $this->getTraderList();

        $tableTrader = new AnalyticsTable;
        $tableTrader->setHeader([
            'name'    => 'Статус',
            'count'   => 'Количество (% от общего количества ЦД)',
        ]);

        $result      = [];
        $traderTotal = 0;
        $moderation  = 0;

        $mainQb = new SqlQueryBuilder;

        // строка Всего
        // по клиенту
        if ($this->client && !$this->action) {
            $actions     = $this->client->getActions();
            $nestedQbArr = [];

            foreach ($actions as $key => $action) {
                $nestedQb       = new SqlQueryBuilder;
                $alias          = 'r' . $key;
                $actionId[$key] = $action->getId();

                $nestedQb
                    ->setOriginQuery("SELECT COUNT(*) AS 'count'
                                      FROM `table2` `$alias`")
                    ->addAndWhere("`$alias`.`action_id` = :actionId" . $key)
                    ->setParameter("actionId" . $key, $actionId[$key])
                    ->addAndWhere("(`$alias`.`status_inner` = :moderation OR `$alias`.`status_inner` = :accepted)")
                    ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                    ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                    ->addAndWhere("`$alias`.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                    ->setParameter("dateStart", $this->dateStart)
                    ->addAndWhere("`$alias`.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                    ->setParameter("dateEnd", $this->dateEnd)
                ;
                array_push($nestedQbArr, $nestedQb);
            }

            $strFrom = '';
    
            for ($i = 0; $i < count($nestedQbArr); $i++) {
                if ($i === (count($nestedQbArr) - 1)) {
                    $strFrom .= '(:nested' . $i . ')';
                    $mainQb
                        ->setOriginQuery("SELECT {$strFrom} as 'count'")
                    ;
                } else {
                    $strFrom .= '(:nested' . $i . ')' . ' + ';
                }

                $mainQb
                    ->addNestedQuery('nested' . $i, $nestedQbArr[$i])
                ;
            }

            $traderTotal = $mainQb
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ;
        }

        // по акции
        else if ($this->client && $this->action || $this->action) {
            $traderTotal = $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count' FROM `table2` r")
                ->addAndWhere('r.`action_id` = :actionId')
                ->setParameter('actionId', $this->actionId)
                ->addAndWhere('(r.`status_inner` = :moderation OR r.`status_inner` = :accepted)')
                ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                ->addAndWhere('r.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ;
        }

        // общая
        else {
            $traderTotal = $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count' FROM `table2` r")
                ->addAndWhere('(r.`status_inner` = :moderation OR r.`status_inner` = :accepted)')
                ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                ->addAndWhere('r.`created_at` >= UNIX_TIMESTAMP(:dateStart)')
                ->setParameter('dateStart', $this->dateStart)
                ->addAndWhere('r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)')
                ->setParameter('dateEnd', $this->dateEnd)
                ->buildQuery($this->manager, ['count' => 'integer'])
                ->getSingleScalarResult()
            ; 
        }
        
        $mainQb->reset();

        // Торговые сети в разрезе
        // по клиенту
        if ($this->client && !$this->action) {
            $actions     = $this->client->getActions();
            $nestedQbArr = [];

            foreach ($actions as $key => $action) {
                $nestedQb       = new SqlQueryBuilder;
                $alias          = 'r' . $key;
                $actionId[$key] = $action->getId();

                $nestedQb
                    ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                    `$alias`.`status_inner` AS 'status',
                                    `$alias`.`trader_id` AS 'trader'
                                    FROM `table2` `$alias` :WHERE
                                    GROUP BY `status`, `trader`")
                    ->addAndWhere("`$alias`.`action_id` = :actionId" . $key)
                    ->setParameter("actionId" . $key, $actionId[$key])
                    ->addAndWhere("(`$alias`.`status_inner` = :moderation OR `$alias`.`status_inner` = :accepted)")
                    ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                    ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                    ->addAndWhere("`$alias`.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                    ->setParameter("dateStart", $this->dateStart)
                    ->addAndWhere("`$alias`.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                    ->setParameter("dateEnd", $this->dateEnd)
                ;
                array_push($nestedQbArr, $nestedQb);
            }

            $strFrom = '';
    
            for ($i = 0; $i < count($nestedQbArr); $i++) {
                if ($i === (count($nestedQbArr) - 1)) {
                    $strFrom .= ':nested' . $i;
                    $mainQb
                        ->setOriginQuery("SELECT SUM(agg.count) AS 'count', agg.status, agg.trader
                                        FROM ({$strFrom}) agg
                                        GROUP BY agg.status, agg.trader")
                    ;
                } else {
                    $strFrom .= ':nested' . $i . ' UNION ALL ';
                }

                $mainQb
                    ->addNestedQuery('nested' . $i, $nestedQbArr[$i])
                ;
            }

            $traderReceipts = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                    'trader' => 'string',
                ])
                ->getResult()
            ;
        }

        // по акции
        else if ($this->client && $this->action || $this->action) {
            $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                  r.`status_inner` AS 'status',
                                  r.`trader_id` AS 'trader'
                                  FROM `table2` r :WHERE
                                  GROUP BY `status`, `trader`")
                ->addAndWhere('r.`action_id` = :actionId')
                ->setParameter('actionId', $this->actionId)
                ->addAndWhere('(r.`status_inner` = :moderation OR r.`status_inner` = :accepted)')
                ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                ->addAndWhere("r.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                ->setParameter("dateStart", $this->dateStart)
                ->addAndWhere("r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                ->setParameter("dateEnd", $this->dateEnd)
            ;

            $traderReceipts = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                    'trader' => 'string',
                ])
                ->getResult()
            ;
        }

        // общая 
        else {
            $mainQb
                ->setOriginQuery("SELECT COUNT(*) AS 'count',
                                  r.`status_inner` AS 'status',
                                  r.`trader_id` AS 'trader'
                                  FROM `table2` r :WHERE
                                  GROUP BY `status`, `trader`")
                ->addAndWhere('(r.`status_inner` = :moderation OR r.`status_inner` = :accepted)')
                ->setParameter('moderation', ReceiptStatusInnerRegistry::STATUS_MODERATION)
                ->setParameter('accepted', ReceiptStatusInnerRegistry::STATUS_ACCEPTED)
                ->addAndWhere("r.`created_at` >= UNIX_TIMESTAMP(:dateStart)")
                ->setParameter("dateStart", $this->dateStart)
                ->addAndWhere("r.`created_at` <= UNIX_TIMESTAMP(:dateEnd)")
                ->setParameter("dateEnd", $this->dateEnd)
            ;

            $traderReceipts = $mainQb
                ->buildQuery($this->manager, [
                    'count'  => 'integer',
                    'status' => 'integer',
                    'trader' => 'string',
                ])
                ->getResult()
            ;
        }

        foreach ($traderReceipts as $row) {
            if ($row['status'] === ReceiptStatusInnerRegistry::STATUS_ACCEPTED) {
                if ($row['trader'] === null) {
                    continue;
                }

                $result[$row['trader']] = [
                    'name'  => $traders[$row['trader']],
                    'count' => $row['count'],
                ];
            } else {
                $moderation += $row['count'];
            }
        }

        $result[] = [
            'name'  => 'Неразмеченные данные',
            'count' => $moderation,
        ];

        if ($traderTotal) {
            foreach ($result as $key => $row) {
                $result[$key]['countRaw'] = $row['count'];
                $result[$key]['count'] = $row['count'] . 
                        ' (' . round(100 * $row['count'] / $traderTotal, 2) . '%)';
            }
        }

        $result[] = [
            'name'     => 'Всего',
            'count'    => $traderTotal,
            'countRaw' => $traderTotal,
        ];

        return $tableTrader->setBody($result);
    }

    /**
     * Статистика для опросов после загрузки чека
     */
    public function buildSurveyAfterReceipt()
    {
        $data = [];

        $ratingQb = new SqlQueryBuilder;
        $rating = $ratingQb
            ->setOriginQuery('SELECT COUNT(sar.`rating`) as "count",
                              sar.`rating` 
                              FROM `survey_after_receipt` sar :WHERE
                              GROUP BY sar.`rating`
                              ORDER BY sar.`rating` ASC 
                              ')
            ->addAndWhereIf('sar.`created_at` >= UNIX_TIMESTAMP(:dateStart)', ['dateStart' => $this->dateStart])
            ->addAndWhereIf('sar.`created_at` <= UNIX_TIMESTAMP(:dateEnd)', ['dateEnd' => $this->dateEnd])
            ->addAndWhereIf('sar.`action_id` = :actionId', ['actionId' => $this->actionId])
            ->buildQuery($this->manager, [
                'count'  => 'integer',
                'rating' => 'string',
            ])->getArrayResult();

        $answersQb = new SqlQueryBuilder;
        $rawAnswers = $answersQb
                ->setOriginQuery('SELECT sar.`reason_for_participation`,
                                  sar.`source_info`
                                  FROM `survey_after_receipt` sar
                                  ')
                ->addAndWhereIf('sar.`created_at` >= UNIX_TIMESTAMP(:dateStart)', ['dateStart' => $this->dateStart])
                ->addAndWhereIf('sar.`created_at` <= UNIX_TIMESTAMP(:dateEnd)', ['dateEnd' => $this->dateEnd])
                ->addAndWhereIf('sar.`action_id` = :actionId', ['actionId' => $this->actionId])
                ->buildQuery($this->manager, [
                    'reason_for_participation'  => 'string',
                    'source_info'               => 'string',
                ])->getArrayResult();
        
        $reasonAnswers = [];
        $sourceAnswers = [];
        
        foreach ($rawAnswers as $answer) {
            $rawReason = explode('; ', $answer['reason_for_participation']);
            $rawSource = explode('; ', $answer['source_info']);
        
            foreach ($rawReason as $el) {
                array_push($reasonAnswers, $el);
            }
        
            foreach ($rawSource as $item) {
                array_push($sourceAnswers, $item);
            }
        }

        $data['ratingData'] = $rating;
        $data['reasonData'] = array_count_values($reasonAnswers);
        $data['sourceData'] = array_count_values($sourceAnswers);

        return $data;
    }
    
    /**
     * Получает список торговых сетей [ id => name ]
     * @return array
     */
    public function getTraderList(): array
    {
        $traders = [];
        /** @var \App\Repository\TraderRepository $repo */
        $repo = $this->doctrine->getRepository(Trader::class);
        $qb = $repo->createQueryBuilder('t');
        $rowData = $qb
                ->select('t.id, t.name')
                ->getQuery()
                ->getResult()
        ;

        foreach ($rowData as $row) {
            $traders[$row['id']] = $row['name'];
        }

        return $traders;
    }
}
