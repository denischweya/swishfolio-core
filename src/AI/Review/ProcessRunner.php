<?php
/**
 * Runs one external process with a hard timeout.
 *
 * @package SwishfolioCore
 */

namespace SwishfolioCore\AI\Review;

/**
 * The proc_open boundary of the visual review (tests replace it). The command
 * is an argv array, so no shell is involved. On timeout the process gets
 * SIGTERM (the renderer closes Chrome on it), then SIGKILL after a grace period.
 */
class ProcessRunner
{
    const GRACE = 5;

    /**
     * @param string[] $argv    Command and arguments.
     * @param int      $timeout Seconds.
     *
     * @return array{exit:int,stdout:string,stderr:string,timedOut:bool}
     */
    public function run(array $argv, $timeout)
    {
        $pipes = [];
        $proc  = @proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); // phpcs:ignore WordPress.PHP.NoSilencedErrors

        if (! is_resource($proc)) {
            return ['exit' => -1, 'stdout' => '', 'stderr' => 'proc_open failed', 'timedOut' => false];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out      = '';
        $err      = '';
        $deadline = microtime(true) + max(1, (int) $timeout);
        $timedOut = false;
        $termAt   = 0.0;
        $exit     = -1;

        while (true) {
            $read   = [$pipes[1], $pipes[2]];
            $write  = null;
            $except = null;
            if (@stream_select($read, $write, $except, 0, 200000)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                foreach ($read as $pipe) {
                    $chunk = (string) fread($pipe, 65536);
                    if ($pipe === $pipes[1]) {
                        $out .= $chunk;
                    } else {
                        $err .= strlen($err) < 65536 ? $chunk : '';
                    }
                }
            }

            $status = proc_get_status($proc);
            if (! $status['running']) {
                $exit = (int) $status['exitcode'];
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                break;
            }

            $now = microtime(true);
            if (! $timedOut && $now > $deadline) {
                $timedOut = true;
                $termAt   = $now;
                proc_terminate($proc, 15);
            } elseif ($timedOut && $now > $termAt + self::GRACE) {
                proc_terminate($proc, 9);
                $termAt = $now + 3600;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return ['exit' => $exit, 'stdout' => $out, 'stderr' => $err, 'timedOut' => $timedOut];
    }
}
