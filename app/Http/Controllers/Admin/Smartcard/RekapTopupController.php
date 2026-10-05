<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Support\SmartcardExcelExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekapTopupController extends Controller
{
    private const PER_PAGE = 10;

    /** Muallimaat: tanpa admin fee (beda Multazam 2000). */
    private const CASH_FEE = 0;

    private const METODE_TOPUP = 'TOP UP CASH';

    private const FIDBANK = '1140002';

    private string $tranTable = 'sccttran';

    private bool $hasHelpdesk = false;

    private ?string $smTopupUserCol = null;

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);

        $this->tranTable = 'sccttran';
        $this->hasHelpdesk = $this->detectHelpdeskColumn($this->tranTable);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        $thnAka = $this->fetchThnAka();
        $kelasOptions = $this->fetchKelasOptions();

        $rows = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $totals = ['topup' => 0, 'fee' => 0, 'grand' => 0];
        $errorMessage = null;

        if ($isSearch) {
            try {
                $rows = $this->fetchRows($filters, $request);
                $totals = $this->sumTotalsSql($filters);
            } catch (\Throwable $e) {
                Log::error('Smartcard RekapTopup fetchRows failed', [
                    'message' => $e->getMessage(),
                    'table' => $this->tranTable,
                ]);
                report($e);
                $errorMessage = 'Gagal memuat data [' . $this->tranTable . ']: ' . $e->getMessage();
            }
        }

        return view('admin.smartcard.rekap_topup.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Rekap TOPUP',
            'dataTitle' => 'Rekap TOPUP',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'totals' => $totals,
            'thnAka' => $thnAka,
            'kelasOptions' => $kelasOptions,
            'errorMessage' => $errorMessage,
        ]);
    }

    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = $this->filtersFromRequest($request);
        $this->tranTable = 'sccttran';
        $this->hasHelpdesk = $this->detectHelpdeskColumn($this->tranTable);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        try {
            $rows = $this->fetchAllRowsForPrint($filters);
            $totals = $this->sumTotalsSql($filters);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Gagal export: ' . $e->getMessage());
        }

        $exportRows = [];
        $no = 1;
        foreach ($rows as $row) {
            $tgl = '';
            if (!empty($row->tgl_transaksi)) {
                try {
                    $tgl = Carbon::parse($row->tgl_transaksi)->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    $tgl = (string) $row->tgl_transaksi;
                }
            }

            $exportRows[] = [
                $no++,
                $row->kelas ?? '',
                $row->gender ?? '',
                $row->lokasi ?? '',
                $row->nis ?? '',
                $row->nama ?? '',
                (int) ($row->topup ?? 0),
                $tgl,
                $row->no_transaksi ?? '',
                $row->user ?? '',
            ];
        }
        $exportRows[] = ['', '', '', '', '', 'TOTAL', (int) ($totals['topup'] ?? 0), '', '', ''];

        return SmartcardExcelExport::download(
            'rekap-topup-' . date('Ymd-His'),
            ['No', 'Kelas', 'Gender', 'Lokasi', 'NIS', 'Nama', 'TOPUP', 'Tgl Transaksi', 'No Transaksi', 'User'],
            $exportRows
        );
    }

    public function printRekap(Request $request): Response|RedirectResponse
    {
        $filters = $this->filtersFromRequest($request);
        $this->tranTable = 'sccttran';
        $this->hasHelpdesk = $this->detectHelpdeskColumn($this->tranTable);
        $this->smTopupUserCol = $this->detectSmTopupUserColumn();

        try {
            $totalCount = (int) $this->baseQuery($filters)->count('t.CUSTID');
            if ($totalCount <= 0) {
                return redirect()
                    ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                    ->with('error', 'Tidak ada data rekap untuk dicetak.');
            }

            $totals = $this->sumTotalsSql($filters);
            $sekolahNama = $this->fetchSekolahNama();

            $pdf = Pdf::loadView('admin.smartcard.rekap_topup.rekap-pdf', [
                'sekolahNama' => $sekolahNama,
                'filters' => $filters,
                'rows' => $this->fetchAllRowsForPrint($filters),
                'totals' => $totals,
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('rekap-topup-uang-saku-' . date('Ymd-His') . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Smartcard RekapTopup printRekap failed', [
                'message' => $e->getMessage(),
                'table' => $this->tranTable,
            ]);
            report($e);

            return redirect()
                ->route('admin.smartcard.rekap-topup.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Gagal mencetak [' . $this->tranTable . ']: ' . $e->getMessage());
        }
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'thn_angkatan' => trim((string) $request->input('thn_angkatan', $request->query('thn_angkatan', ''))),
            'kelas_id' => trim((string) $request->input('kelas_id', $request->query('kelas_id', ''))),
            'nis' => trim((string) $request->input('nis', $request->query('nis', ''))),
            'nama' => trim((string) $request->input('nama', $request->query('nama', ''))),
            'dari_tanggal' => trim((string) $request->input('dari_tanggal', $request->query('dari_tanggal', ''))),
            'sampai_tanggal' => trim((string) $request->input('sampai_tanggal', $request->query('sampai_tanggal', ''))),
        ];
    }

    private function detectHelpdeskColumn(string $table): bool
    {
        return $this->detectColumn($table, 'HELPDESK');
    }

    private function detectColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function detectSmTopupUserColumn(): ?string
    {
        try {
            if (!Schema::connection('DATA_MYSQL')->hasTable('sm_topup')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        foreach (['users', 'user', 'User'] as $col) {
            if ($this->detectColumn('sm_topup', $col)) {
                return $col;
            }
        }

        return null;
    }

    private function baseQuery(array $filters)
    {
        $t = $this->tranTable;

        $sekolah = DB::connection('DATA_MYSQL')
            ->table('mst_sekolah')
            ->selectRaw('TRIM(CODE01) as code01, MAX(NULLIF(TRIM(DESC01), \'\')) as nama_sekolah')
            ->groupByRaw('TRIM(CODE01)');

        $query = DB::connection('DATA_MYSQL')
            ->table("{$t} as t")
            ->join('scctcust', 't.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoinSub($sekolah, 'sk', function ($join) {
                $join->on(DB::raw('sk.code01'), '=', DB::raw('TRIM(scctcust.CODE01)'));
            })
            ->where('t.KREDIT', '>', 0)
            ->whereRaw("UPPER(TRIM(COALESCE(t.METODE, ''))) <> 'ADMINFEE'")
            ->where(function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(t.FIDBANK, ''))) = 'TOPUP'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(t.METODE, ''))) LIKE 'TOP UP%'")
                    ->orWhere(function ($q2) {
                        $q2->whereRaw("UPPER(TRIM(COALESCE(t.FIDBANK, ''))) = 'CASH'")
                            ->whereRaw("UPPER(TRIM(COALESCE(t.METODE, ''))) LIKE 'TOP UP%'");
                    })
                    ->orWhere(function ($q2) {
                        $q2->whereRaw('TRIM(COALESCE(t.FIDBANK, \'\')) = ?', [self::FIDBANK])
                            ->where(function ($q3) {
                                $q3->whereRaw('UPPER(TRIM(t.METODE)) = ?', [self::METODE_TOPUP])
                                    ->orWhereRaw('UPPER(TRIM(t.METODE)) = ?', ['TOP UP CASHLESS']);
                            });
                    });
            });

        if ($this->smTopupUserCol !== null) {
            $query->leftJoin('sm_topup as tp', function ($join) {
                $join->whereRaw(
                    'TRIM(tp.TOPUPNO) = TRIM(COALESCE(NULLIF(TRIM(t.TRANSNO), \'\'), t.NOREFF))'
                );
            });
        }

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function selectColumns(): array
    {
        $merchantCol = $this->detectColumn($this->tranTable, 'MERCHANT')
            ? 't.MERCHANT'
            : ($this->detectColumn($this->tranTable, 'MERCH') ? 't.MERCH' : null);

        // Prioritas: sm_topup.users (login cyber_key) → MERCHANT → HELPDESK
        $userParts = [];
        if ($this->smTopupUserCol !== null) {
            $userParts[] = "NULLIF(TRIM(tp.`{$this->smTopupUserCol}`), '')";
        }
        if ($merchantCol !== null) {
            $userParts[] = "NULLIF(TRIM({$merchantCol}), '')";
        }
        if ($this->hasHelpdesk) {
            $userParts[] = "NULLIF(TRIM(t.HELPDESK), '')";
        }
        $userExpr = $userParts === []
            ? "'-'"
            : 'COALESCE(' . implode(', ', $userParts) . ", '-')";

        return [
            't.urut',
            'scctcust.NOCUST as nis',
            'scctcust.NMCUST as nama',
            't.KREDIT as topup',
            't.TRXDATE as tgl_transaksi',
            DB::raw("COALESCE(NULLIF(TRIM(t.TRANSNO), ''), NULLIF(TRIM(t.NOREFF), ''), '-') as no_transaksi"),
            $this->hasHelpdesk
                ? 't.HELPDESK as helpdesk'
                : DB::raw("'' as helpdesk"),
            't.METODE as metode',
            DB::raw('0 as fee_debet'),
            DB::raw("COALESCE(NULLIF(TRIM(scctcust.DESC03), ''), NULLIF(TRIM(scctcust.DESC02), ''), '-') as kelas"),
            DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE04), ''), '-') as gender"),
            DB::raw("COALESCE(NULLIF(TRIM(sk.nama_sekolah), ''), NULLIF(TRIM(scctcust.DESC01), ''), '-') as lokasi"),
            DB::raw("{$userExpr} as user_raw"),
        ];
    }

    private function fetchRows(array $filters, Request $request): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('t.TRXDATE')
            ->orderByDesc('t.urut')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($row) => $this->mapRow($row));
    }

    private function mapRow(object $row): object
    {
        $topupGross = (int) ($row->topup ?? 0);
        // Muallimaat: tanpa admin fee — tampilkan nominal utuh
        $row->topup = $topupGross;
        $row->fee = 0;
        $row->total = $topupGross;

        $rawUser = trim((string) ($row->user_raw ?? ''));
        if ($rawUser === '' || $rawUser === '-') {
            $parsed = $this->parseUser((string) ($row->helpdesk ?? ''));
            $row->user = $parsed;
        } elseif (preg_match('/User:\s*([^\s|]+)/i', $rawUser, $m)) {
            $row->user = trim($m[1]) !== '' ? trim($m[1]) : $rawUser;
        } else {
            // sm_topup.users sudah login username (bukan ket/nama)
            $row->user = $rawUser;
        }

        return $row;
    }

    /** @return array{topup: int, fee: int, grand: int} */
    private function sumPageTotals(LengthAwarePaginator $paginator): array
    {
        $topup = 0;
        $fee = 0;
        foreach ($paginator->items() as $row) {
            $topup += (int) ($row->topup ?? 0);
            $fee += (int) ($row->fee ?? 0);
        }

        return [
            'topup' => $topup,
            'fee' => $fee,
            'grand' => max(0, $topup - $fee),
        ];
    }

    private function sumTotalsSql(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->selectRaw('CAST(COALESCE(SUM(t.KREDIT), 0) AS SIGNED) as topup_sum')
            ->first();

        $topup = (int) ($row->topup_sum ?? 0);

        return [
            'topup' => $topup,
            'fee' => 0,
            'grand' => $topup,
        ];
    }

    private function fetchAllRowsForPrint(array $filters): \Illuminate\Support\Collection
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('t.TRXDATE')
            ->orderByDesc('t.urut')
            ->get()
            ->map(fn ($row) => $this->mapRow($row));
    }

    private function parseFee(string $helpdesk, string $metode): int
    {
        if (preg_match('/Biaya:\s*(\d+)/i', $helpdesk, $m)) {
            return (int) $m[1];
        }

        $m = strtoupper(trim($metode));

        return $m === 'CASH'
            || $m === self::METODE_TOPUP
            || $m === 'TOP UP CASHLESS'
            || str_starts_with($m, 'TOP UP')
            ? self::CASH_FEE
            : 0;
    }

    private function parseUser(string $helpdesk): string
    {
        if (preg_match('/User:\s*([^\s|]+)/i', $helpdesk, $m)) {
            $user = trim($m[1]);
            if ($user !== '') {
                return $user;
            }
        }

        return '-';
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(scctcust.CODE01) = ?', [$unit])
                ->orWhereRaw('TRIM(scctcust.CODE02) = ?', [$unit]);
        });
    }

    private function applyFilters($query, array $filters): void
    {
        if ($filters['nis'] !== '') {
            $query->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%');
        }

        if ($filters['nama'] !== '') {
            $query->where('scctcust.NMCUST', 'like', '%' . $filters['nama'] . '%');
        }

        if ($filters['thn_angkatan'] !== '') {
            $this->applyAngkatanLike($query, $filters['thn_angkatan']);
        }

        if ($filters['dari_tanggal'] !== '') {
            $from = $this->parseDate($filters['dari_tanggal']);
            if ($from) {
                $query->where('t.TRXDATE', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('t.TRXDATE', '<=', $to->endOfDay());
            }
        }

        if ($filters['kelas_id'] !== '') {
            $this->applyKelasFilter($query, $filters['kelas_id']);
        }
    }

    private function applyAngkatanLike($query, string $value): void
    {
        $full = trim($value);
        $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
        $query->where(function ($q) use ($full, $base) {
            $q->whereRaw('TRIM(scctcust.DESC04) = ?', [$full]);
            if ($base !== '' && $base !== $full) {
                $q->orWhereRaw('TRIM(scctcust.DESC04) = ?', [$base])
                    ->orWhereRaw("REPLACE(TRIM(scctcust.DESC04), ' ', '') LIKE ?", [str_replace(' ', '', $base) . '%']);
            }
        });
    }

    private function applyKelasFilter($query, string $kelasId): void
    {
        $kelas = DB::connection('DATA_MYSQL')
            ->table('mst_kelas')
            ->where('id', (int) $kelasId)
            ->first(['id', 'unit', 'jenjang', 'kelas']);

        if (!$kelas) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unit = trim((string) ($kelas->unit ?? ''));
        $jenjang = trim((string) ($kelas->jenjang ?? ''));
        $kelasNama = trim((string) ($kelas->kelas ?? ''));

        $query->where(function ($q) use ($kelasId, $unit, $jenjang, $kelasNama) {
            $q->whereRaw('TRIM(scctcust.CODE03) = ?', [(string) $kelasId]);
            if ($unit !== '' && $jenjang !== '' && $kelasNama !== '') {
                $q->orWhere(function ($q2) use ($unit, $jenjang, $kelasNama) {
                    $q2->whereRaw('TRIM(scctcust.CODE02) = ?', [$unit])
                        ->whereRaw('TRIM(scctcust.DESC02) = ?', [$jenjang])
                        ->whereRaw('TRIM(scctcust.DESC03) = ?', [$kelasNama]);
                });
            }
        });
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<object{thn_aka: string}> */
    private function fetchThnAka(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->get(['thn_aka'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<object> */
    private function fetchKelasOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_kelas')
                ->orderBy('unit')
                ->orderBy('jenjang')
                ->orderBy('kelas')
                ->get(['id', 'unit', 'jenjang', 'kelas'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchSekolahNama(): string
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit !== '') {
            try {
                $nama = DB::connection('DATA_MYSQL')
                    ->table('mst_sekolah')
                    ->where(function ($q) use ($unit) {
                        $q->whereRaw('TRIM(CODE01) = ?', [$unit])
                            ->orWhereRaw('TRIM(DESC01) = ?', [$unit]);
                    })
                    ->value('DESC01');

                if ($nama !== null && trim((string) $nama) !== '') {
                    return trim((string) $nama);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'Al-Multazam';
    }
}
