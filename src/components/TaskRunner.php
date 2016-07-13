<?php
namespace vm\cron\components;

use Cron\CronExpression;

/**
 * Class TaskRunner
 * Runs tasks and handles time expression
 * @author  mult1mate
 * @package vm\cron
 * Date: 07.02.16
 * Time: 12:50
 */
class TaskRunner
{
    /**
     * Runs active tasks if current time matches with time expression
     *
     * @param array $tasks
     */
    public static function checkAndRunTasks($tasks)
    {
        $date = date('Y-m-d H:i:s');
        foreach ($tasks as $t) {
            /**
             * @var TaskInterface $t
             */
            if (TaskInterface::TASK_STATUS_ACTIVE != $t->getStatus()) {
                continue;
            }

            $cron = CronExpression::factory($t->getTime());

            if ($cron->isDue($date)) {
                self::runTask($t);
            }
        }
    }

    /**
     * Runs task and returns output
     *
     * @param TaskInterface $task
     *
     * @return string
     */
    public static function runTask($task)
    {
        $run = $task->createTaskRun();
        $run->setTaskId($task->getTaskId());
        $run->setTs(date('Y-m-d H:i:s'));
        $run->setStatus(TaskRunInterface::RUN_STATUS_STARTED);
        $run->saveTaskRun();
        $runFinalStatus = TaskRunInterface::RUN_STATUS_COMPLETED;

        ob_start();
        $timeBegin = microtime(true);

        $result = self::parseAndRunCommand($task->getCommand());
        if (!$result) {
            $runFinalStatus = TaskRunInterface::RUN_STATUS_ERROR;
        }

        $output = ob_get_clean();
        $run->setOutput($output);

        $timeEnd = microtime(true);
        $time     = round(($timeEnd - $timeBegin), 2);
        $run->setExecutionTime($time);

        $run->setStatus($runFinalStatus);
        $run->saveTaskRun();

        return $output;
    }

    /**
     * Parses given command, creates new class object and calls its method via call_user_func_array
     *
     * @param string $command
     *
     * @return mixed
     */
    public static function parseAndRunCommand($command)
    {
        try {
            list($class, $method, $args) = TaskManager::parseCommand($command);
            if (!class_exists($class)) {
                TaskLoader::loadController($class);
            }

            $obj = new $class();
            if (!method_exists($obj, $method)) {
                throw new TaskManagerException('method ' . $method . ' not found in class ' . $class);
            }

            return call_user_func_array([$obj, $method], $args);
        } catch (\Exception $e) {
            echo 'Caught an exception: ' . get_class($e) . ': ' . PHP_EOL . $e->getMessage() . PHP_EOL;

            return false;
        }
    }

    /**
     * Returns next run dates for time expression
     *
     * @param string $time
     * @param int    $count
     *
     * @return array
     */
    public static function getRunDates($time, $count = 10)
    {
        try {
            $cron  = CronExpression::factory($time);
            $dates = $cron->getMultipleRunDates($count);
        } catch (\Exception $e) {
            return [];
        }

        return $dates;
    }
}