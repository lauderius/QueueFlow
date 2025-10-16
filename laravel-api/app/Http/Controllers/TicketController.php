<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'queue_id' => 'required|integer|min:1',
            'person'   => 'nullable|string|max:255',
            'notes'    => 'nullable|string',
            'ext_id'   => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($data) {
            $queue = DB::table('queues')->where('id', $data['queue_id'])->lockForUpdate()->first();
            if (!$queue) {
                return response()->json(['error' => 'Fila inexistente'], 404);
            }
            $newNumber = ((int) $queue->last_number) + 1;
            DB::table('queues')->where('id', $data['queue_id'])->update(['last_number' => $newNumber]);

            $extId = $data['ext_id'] ?? '';
            if ($extId === '') {
                $extId = 't_'.Str::random(12).dechex(time());
            }

            $ticketId = DB::table('tickets')->insertGetId([
                'ext_id' => $extId,
                'queue_id' => $data['queue_id'],
                'number' => $newNumber,
                'status' => 'waiting',
                'person' => $data['person'] ?? null,
                'notes'  => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ticket_updates')->insert([
                'ticket_id' => $ticketId,
                'msg' => 'Ticket criado',
                'created_at' => now(),
            ]);

            return response()->json([
                'ticket' => [
                    'id' => $ticketId,
                    'ext_id' => $extId,
                    'queue_id' => (int) $data['queue_id'],
                    'number' => $newNumber,
                    'status' => 'waiting',
                    'person' => $data['person'] ?? null,
                    'notes'  => $data['notes'] ?? null,
                ],
                'queue' => [
                    'id' => (int) $queue->id,
                    'name' => $queue->name,
                    'group_name' => $queue->group_name,
                    'avg_service_sec' => (int) $queue->avg_service_sec,
                ],
            ], 201);
        });
    }

    public function show(string $extId)
    {
        $t = DB::table('tickets as t')
            ->join('queues as q', 'q.id', '=', 't.queue_id')
            ->select('t.*', 'q.name as q_name', 'q.group_name as q_group', 'q.avg_service_sec as q_avg')
            ->where('t.ext_id', $extId)
            ->first();
        if (!$t) {
            return response()->json(['error' => 'Ticket não encontrado'], 404);
        }

        $pos = 0; $eta = 0;
        if ($t->status === 'waiting') {
            $ahead = DB::table('tickets')
                ->where('queue_id', $t->queue_id)
                ->where('status', 'waiting')
                ->where(function ($q) use ($t) {
                    $q->where('created_at', '<', $t->created_at)
                      ->orWhere(function ($q2) use ($t) {
                          $q2->where('created_at', $t->created_at)->where('id', '<', $t->id);
                      });
                })
                ->count();
            $pos = $ahead + 1;
            $serving = DB::table('tickets')->where('queue_id', $t->queue_id)->where('status', 'serving')->count();
            $avg = max(30, min(1200, (int) $t->q_avg));
            $totalAhead = max(0, $pos - 1) + ($serving > 0 ? 0.5 : 0);
            $eta = (int) round($totalAhead * $avg);
        }

        $updates = DB::table('ticket_updates')->select('msg', DB::raw('created_at as ts'))
            ->where('ticket_id', $t->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'ticket' => [
                'ext_id' => $t->ext_id,
                'queue_id' => (int) $t->queue_id,
                'number' => (int) $t->number,
                'status' => $t->status,
                'person' => $t->person,
                'notes'  => $t->notes,
                'created_at' => $t->created_at,
                'started_at' => $t->started_at,
                'finished_at' => $t->finished_at,
            ],
            'queue' => [
                'id' => (int) $t->queue_id,
                'name' => $t->q_name,
                'group_name' => $t->q_group,
                'avg_service_sec' => (int) $t->q_avg,
            ],
            'position' => $pos,
            'eta_seconds' => $eta,
            'updates' => $updates,
        ]);
    }

    public function updateStatus(Request $request, string $extId)
    {
        $data = $request->validate([
            'status' => 'required|string|in:waiting,serving,done,cancel',
        ]);
        $status = $data['status'];

        return DB::transaction(function () use ($extId, $status) {
            $t = DB::table('tickets as t')
                ->join('queues as q', 'q.id', '=', 't.queue_id')
                ->select('t.*', 'q.avg_service_sec', 'q.id as qid')
                ->where('t.ext_id', $extId)
                ->lockForUpdate()
                ->first();
            if (!$t) {
                return response()->json(['error' => 'Ticket não encontrado'], 404);
            }

            if ($status === 'serving') {
                DB::table('tickets')->where('id', $t->id)->update([
                    'status' => 'serving', 'started_at' => $t->started_at ?? now(), 'updated_at' => now(),
                ]);
            } elseif ($status === 'done') {
                DB::table('tickets')->where('id', $t->id)->update([
                    'status' => 'done', 'finished_at' => now(), 'updated_at' => now(),
                ]);
            } elseif ($status === 'cancel') {
                DB::table('tickets')->where('id', $t->id)->update([
                    'status' => 'cancel', 'updated_at' => now(),
                ]);
            } else { // waiting
                DB::table('tickets')->where('id', $t->id)->update([
                    'status' => 'waiting', 'updated_at' => now(),
                ]);
            }

            DB::table('ticket_updates')->insert([
                'ticket_id' => $t->id, 'msg' => 'Status: '.$status, 'created_at' => now(),
            ]);

            if ($status === 'done') {
                $tt = DB::table('tickets')->select('started_at','finished_at','queue_id')->where('id', $t->id)->first();
                if ($tt && $tt->started_at && $tt->finished_at) {
                    $dur = (int) DB::selectOne('SELECT TIMESTAMPDIFF(SECOND, ?, ?) AS dur', [$tt->started_at, $tt->finished_at])->dur;
                    $dur = max(10, $dur);
                    DB::table('queue_history')->insert([
                        'queue_id' => $tt->queue_id,
                        'duration_sec' => $dur,
                        'created_at' => now(),
                    ]);
                    $prev = (int) (DB::table('queues')->lockForUpdate()->where('id', $tt->queue_id)->value('avg_service_sec') ?? 180);
                    $new = (int) round($prev * 0.7 + $dur * 0.3);
                    $new = max(30, min(1200, $new));
                    DB::table('queues')->where('id', $tt->queue_id)->update(['avg_service_sec' => $new]);
                }
            }

            return response()->json(['ok' => true]);
        });
    }

    public function next(int $queue)
    {
        return DB::transaction(function () use ($queue) {
            $serving = DB::table('tickets')->where('queue_id', $queue)->where('status', 'serving')->orderBy('started_at')->lockForUpdate()->first();
            if ($serving) {
                return response()->json(['ticket' => $serving]);
            }
            $nxt = DB::table('tickets')->where('queue_id', $queue)->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();
            if (!$nxt) {
                return response()->json(['ticket' => null]);
            }
            DB::table('tickets')->where('id', $nxt->id)->update([
                'status' => 'serving', 'started_at' => $nxt->started_at ?? now(), 'updated_at' => now(),
            ]);
            DB::table('ticket_updates')->insert([
                'ticket_id' => $nxt->id, 'msg' => 'Status: serving', 'created_at' => now(),
            ]);
            $row = DB::table('tickets')->where('id', $nxt->id)->first();
            return response()->json(['ticket' => $row]);
        });
    }

    public function current(int $queue)
    {
        $row = DB::table('tickets')->where('queue_id', $queue)->where('status', 'serving')->orderBy('started_at')->first();
        return response()->json(['ticket' => $row ?: null]);
    }

    public function waiting(Request $request, int $queue)
    {
        $limit = (int) $request->query('limit', 15);
        $limit = max(1, min(50, $limit));
        $rows = DB::table('tickets')->where('queue_id', $queue)->where('status', 'waiting')->orderBy('created_at')->orderBy('id')->limit($limit)->get();
        return response()->json(['tickets' => $rows]);
    }
}
