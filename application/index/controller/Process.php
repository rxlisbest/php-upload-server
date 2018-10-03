<?php

namespace app\index\controller;

use app\common\model\Bucket;
use app\common\model\File;
use app\common\model\Persistent;
use app\common\model\PersistentPipeline;

use Curl\Curl;
use Rxlisbest\FFmpegTranscoding\Slice;
use Rxlisbest\FFmpegTranscoding\Transcoding;
use Pheanstalk\Pheanstalk;

use think\facade\Config;
use think\Controller;

class Process extends Controller
{
    /**
     * daemon swoole process
     * @name: index
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function index()
    {
        // 连接beanstalkd
        $config = Config::get('beanstalkd.');
        $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
        // 监听当前进程的tube
        $tube = $pheanstalk
            ->useTube(config('persistent_pipeline.parent_tube'));

        $where = [];
        $where['status'] = PersistentPipeline::STATUS_ON;
        $persistent_pipeline_list = PersistentPipeline::all($where);
        foreach ($persistent_pipeline_list as $k => $v) {
            $tube->put($v->id);
        }
        // add process
        $process = new \swoole_process(function (\swoole_process $worker) use ($config) {
            $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
            // 监听当前进程的tube
            $tube = $pheanstalk
                ->watch(config('persistent_pipeline.parent_tube'))
                ->ignore('default');

            while (1) {
                $job = $tube->reserve(0);
                if (!$job) {
                    sleep(1);
                    continue;
                }
                $pheanstalk->delete($job);
                $persistent_pipeline_id = $job->getData();
                // transcoding process
                $process = new \swoole_process(function (\swoole_process $worker) use ($config, $persistent_pipeline_id) {
                    $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
                    // 监听当前进程的tube
                    $tube = $pheanstalk
                        ->watch($persistent_pipeline_id)
                        ->ignore('default');

                    while (1) {
                        $job = $tube->reserve(0);
                        if (!$job) {
                            sleep(1);
                            continue;
                        }
                        $pheanstalk->delete($job);
                        $id = $job->getData();
                        $this->transcoding($id);
                    }
                }, false);
                \swoole_process::daemon();
                $process->start();
                $pid = $process->pid;

                // update table persistent_pipeline field pid
                $persistent_pipeline = PersistentPipeline::get(['id' => $persistent_pipeline_id]);
                $persistent_pipeline->pid = $pid;
                $persistent_pipeline->save();
            }
        }, false);
        \swoole_process::daemon();
        $process->start();

        // delete process
        $process = new \swoole_process(function (\swoole_process $worker) use ($config) {
            $pheanstalk = new Pheanstalk($config['hostname'], $config['hostport']);
            // 监听当前进程的tube
            $tube = $pheanstalk
                ->watch(config('persistent_pipeline.parent_delete_tube'))
                ->ignore('default');

            while (1) {
                $job = $tube->reserve(0);
                if (!$job) {
                    sleep(1);
                    continue;
                }
                $pheanstalk->delete($job);
                $pid = $job->getData();
                \swoole_process::kill($pid, $signo = SIGTERM);
            }
        }, false);
        \swoole_process::daemon();
        $process->start();
        //        swoole_event_add($process->pipe, function($pipe) use($process) {
        //            echo sprintf(" code: %s\n", $process->read());
        ////            swoole_event_del($pipe);
        //        });
    }

    /**
     * transcoding
     * @name: transcoding
     * @param $id
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    private function transcoding($id)
    {
        $persistent = Persistent::get($id);

        $input_bucket_dir = sprintf('%s%s/%s/', $persistent->upload_dir, $persistent->user_id, $persistent->input_bucket);
        $output_bucket_dir = sprintf('%s%s/%s/', $persistent->upload_dir, $persistent->user_id, $persistent->output_bucket);
        if (!file_exists($output_bucket_dir)) {
            mkdir($output_bucket_dir, 0777, true);
            chmod($output_bucket_dir, 0777);
        }
        $persistentOps = explode('|', $persistent['ops']);
        $option_arr = explode('/', $persistentOps[0]);
        $option = [];
        for ($i = 0; $i < count($option_arr) / 2; $i++) {
            $option[$option_arr[2 * $i]] = $option_arr[2 * $i + 1];
        }
        $ffmpeg_config = Config::get('ffmpeg.');
        if ($option['avthumb'] == 'm3u8') {
            $transcoding = new Slice(['option' => $option, 'ffmpeg' => $ffmpeg_config]);

            if(substr($persistent->output_key, strrpos($persistent->output_key, '.')) != '.m3u8'){
                $persistent->output_key .= '.m3u8';
            }
        } else {
            $transcoding = new Transcoding(['option' => $option, 'ffmpeg' => $ffmpeg_config]);
        }
        $input = $input_bucket_dir . $persistent->input_key;
        $output = $output_bucket_dir . $persistent->output_key;

        // if the input file is not exists, then continue;
        if (!is_file($input)) {
            return false;
        }
        $result = $transcoding->exec($input, $output);

        // update table persistent field status
        if ($result === 0) {
            $persistent->status = Persistent::STATUS_SUCCESS;
            $persistent->output_hash = hash_file('sha1', $output);
        } else {
            $persistent->status = Persistent::STATUS_FAIL;
        }
        $persistent->save();
        // get bucket info
        $bucket = Bucket::get(['name' => $persistent->output_bucket, 'user_id' => $persistent->user_id]);

        // insert into table file
        $file_model = new File();
        $file_model->bucket_id = $bucket->id;
        $file_model->name = $persistent->output_key;
        $file_model->save();

        // notify client
        $this->notify($persistent->persistent_id);
    }

    /**
     * persistent notify
     * @name: notify
     * @param $persistent_id
     * @return void
     * @author: RuiXinglong <ruixl@soocedu.com>
     * @time: 2017-06-19 10:00:00
     */
    public function notify($persistent_id)
    {
        $persistent_model = new Persistent();
        $data = $persistent_model->getInfo($persistent_id);
        if ($data['code'] != Persistent::STATUS_WAITING) {
            $curl = new Curl();
            $curl->setHeader('Content-Type', 'application/json');
            $result = $curl->post($data['notifyUrl'], $data);
            $where = [];
            $where['persistent_id'] = $persistent_id;
            if (!$result) {
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_FAIL], $where);
            } else {
                $persistent_model->save(['notify_status' => Persistent::NOTIFY_STATUS_SUCCESS], $where);
            }
        }
    }
}
