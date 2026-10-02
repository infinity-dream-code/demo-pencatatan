<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TapAmbilRutinController extends Controller
{
    private const TRAN_TABLE = 'sccttran';

    public function index(): View
    {
        return view('admin.smartcard.tap_ambil_rutin.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'TAP Ambil Rutin',
            'dataTitle' => 'TAP Ambil Rutin',
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $tapId = trim((string) $request->input('tap_id', ''));
        if ($tapId === '') {
            return $this->fail('TAP ID wajib diisi.', 422);
        }

        $card = $this->fetchCard($tapId);
        if (!$card) {
            return $this->fail('Kartu tidak ditemukan.', 404);
        }

        if ($this->isBlocked($card)) {
            return $this->fail('Kartu terblokir.', 403, ['blocked' => true]);
        }

        $custid = (int) ($card->custid ?? 0);
        if (!$this->siswaInScope($custid)) {
            return $this->fail('Kartu tidak termasuk unit sekolah ini.', 403);
        }

        $saldo = $this->fetchSaldo($custid);
        $batasCash = $this->fetchBatasCash();
        $maxAmbil = min($saldo, $batasCash);

        return response()->json([
            'ok' => true,
            'data' => [
                'tap_id' => $tapId,
                'custid' => $custid,
                'nama' => trim((string) ($card->nama ?? '')),
                'nis' => trim((string) ($card->nis ?? '')),
                'saldo' => $saldo,
                'batas_cash' => $batasCash,
                'max_ambil' => $maxAmbil,
            ],
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $tapId = trim((string) $request->input('tap_id', ''));
        $pin = trim((string) $request->input('pin', ''));
        $ambil = $this->parseAmount($request->input('ambil', ''));

        if ($tapId === '') {
            return $this->fail('TAP ID wajib diisi.', 422);
        }
        if ($pin === '') {
            return $this->fail('PIN wajib diisi.', 422);
        }
        if ($ambil <= 0) {
            return $this->fail('Nominal ambil harus lebih dari 0.', 422);
        }

        $card = $this->fetchCard($tapId);
        if (!$card) {
            return $this->fail('Kartu tidak ditemukan.', 404);
        }

        if ($this->isBlocked($card)) {
            return $this->fail('Kartu terblokir.', 403, ['blocked' => true]);
        }

        $custid = (int) ($card->custid ?? 0);
        if (!$this->siswaInScope($custid)) {
            return $this->fail('Kartu tidak termasuk unit sekolah ini.', 403);
        }

        $storedPin = trim((string) ($card->pin ?? ''));
        if ($storedPin === '' || $pin !== $storedPin) {
            return $this->fail('PIN salah.', 403, ['pin_error' => true]);
        }

        $saldo = $this->fetchSaldo($custid);
        if ($ambil > $saldo) {
            return $this->fail('Saldo tidak mencukupi. Saldo: Rp ' . number_format($saldo, 0, ',', '.'), 422);
        }

        $batasCash = $this->fetchBatasCash();
        if ($batasCash <= 0) {
            return $this->fail('Batas cash belum diset untuk periode ini.', 422);
        }
        if ($ambil > $batasCash) {
            return $this->fail('Nominal melebihi batas cash (Rp ' . number_format($batasCash, 0, ',', '.') . ').', 422);
        }

        $trxDate = now();
        $transNo = $this->generateTransNo($trxDate);
        $user = trim((string) session('auth_username', session('auth_name', '')));
        $helpdesk = $user !== '' ? 'User:' . $user : '';

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use ($custid, $trxDate, $ambil, $transNo, $helpdesk) {
                DB::connection('DATA_MYSQL')->table(self::TRAN_TABLE)->insert([
                    'CUSTID' => $custid,
                    'METODE' => 'Cash',
                    'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                    'KREDIT' => 0,
                    'DEBET' => $ambil,
                    'TRANSNO' => $transNo,
                    'NOREFF' => $transNo,
                    'HELPDESK' => $helpdesk,
                    'FIDBANK' => 'cash',
                    'KDCHANNEL' => 0,
                    'REFFBANK' => '',
                ]);
            });
        } catch (\Throwable $e) {
            return $this->fail('Gagal menyimpan transaksi: ' . $e->getMessage(), 500);
        }

        $saldoBaru = $this->fetchSaldo($custid);

        return response()->json([
            'ok' => true,
            'message' => 'Pengambilan cash berhasil.',
            'data' => [
                'trans_no' => $transNo,
                'nama' => trim((string) ($card->nama ?? '')),
                'ambil' => $ambil,
                'saldo_baru' => $saldoBaru,
            ],
        ]);
    }

    private function fetchCard(string $tapId): ?object
    {
        return DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->join('scctcust', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
            ->where('sm_pin.PID', $tapId)
            ->first([
                'sm_pin.CUSTID as custid',
                'sm_pin.PIN as pin',
                'sm_pin.BLOKIR as blokir',
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
            ]);
    }

    private function isBlocked(object $card): bool
    {
        $blokir = $card->blokir ?? null;

        return (int) $blokir === 1;
    }

    private function fetchSaldo(int $custid): int
    {
        $row = DB::connection('DATA_MYSQL')
            ->table(self::TRAN_TABLE)
            ->where('CUSTID', $custid)
            ->selectRaw('CAST(COALESCE(SUM(KREDIT), 0) AS SIGNED) - CAST(COALESCE(SUM(DEBET), 0) AS SIGNED) AS saldo')
            ->first();

        return (int) ($row->saldo ?? 0);
    }

    private function fetchBatasCash(): int
    {
        $periode = now()->format('Ym');

        $row = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->where('periode', $periode)
            ->where('aktif', 1)
            ->orderByDesc('urut')
            ->first(['batas_cash']);

        if ($row) {
            return (int) ($row->batas_cash ?? 0);
        }

        $fallback = DB::connection('DATA_MYSQL')
            ->table('sm_batasan')
            ->where('aktif', 1)
            ->orderByDesc('periode')
            ->first(['batas_cash']);

        return (int) ($fallback->batas_cash ?? 0);
    }

    private function generateTransNo(Carbon $trxDate): string
    {
        $prefix = $trxDate->format('Ymd');

        $last = DB::connection('DATA_MYSQL')
            ->table(self::TRAN_TABLE)
            ->where(function ($q) use ($prefix) {
                $q->whereRaw('TRIM(TRANSNO) LIKE ?', [$prefix . '%'])
                    ->orWhereRaw('TRIM(NOREFF) LIKE ?', [$prefix . '%']);
            })
            ->orderByDesc('TRANSNO')
            ->value('TRANSNO');

        $seq = 1;
        if ($last !== null && $last !== '') {
            $last = trim((string) $last);
            if (str_starts_with($last, $prefix) && strlen($last) > strlen($prefix)) {
                $tail = substr($last, strlen($prefix));
                if (ctype_digit($tail)) {
                    $seq = (int) $tail + 1;
                }
            }
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    private function siswaInScope(int $custid): bool
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return true;
        }

        return DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->where(function ($q) use ($unit) {
                $q->whereRaw('TRIM(CAST(CODE01 AS CHAR)) = ?', [$unit])
                    ->orWhereRaw('TRIM(CAST(CODE02 AS CHAR)) = ?', [$unit]);
            })
            ->exists();
    }

    private function parseAmount(mixed $value): int
    {
        $raw = preg_replace('/[^\d]/', '', (string) $value) ?? '';

        return (int) $raw;
    }

    private function fail(string $message, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => false,
            'message' => $message,
        ], $extra), $status);
    }
}
