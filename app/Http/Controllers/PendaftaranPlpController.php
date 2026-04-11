<?php

namespace App\Http\Controllers;

use App\Models\Keminatan;
use App\Models\PendaftaranPlp;
use App\Models\Smk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PendaftaranPlpController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $smks = Smk::orderBy('name')->get(['id', 'name']);
        $keminatans = Keminatan::orderBy('name')->get(['id', 'name']);
        $pendaftaranPlp = PendaftaranPlp::where('user_id', Auth::id())->latest()->get();

        if (request()->wantsJson()) {
            $pendaftaranPlpApi = PendaftaranPlp::with([
                'user',
                'pilihanSmk1',
                'pilihanSmk2',
                'keminatan',
                'penempatanSmk',
            ])->where('user_id', Auth::id())->latest()->get();

            $formatted = $pendaftaranPlpApi
                ->map(fn($item) => $this->formatPendaftaranForApi($item))
                ->values();

            return response()->json($formatted, 200);
        }

        return Inertia::render(
            'PendaftaranPlp',
            ['user' => $user, 'smks' => $smks, 'keminatans' => $keminatans, 'pendaftaranPlp' => $pendaftaranPlp]
        );
    }

    public function indexBySmk($id)
    {
        $smks = Smk::withCount('pendaftaranPlps')->withCount('penanggungJawabs')->orderBy('name', 'asc')->get();;
        $pendaftaranPlp = PendaftaranPlp::where('penempatan', $id)->with('user.mahasiswaPamong')->latest()->get();

        if (request()->wantsJson()) {
            $formatted = $pendaftaranPlp
                ->map(fn($item) => $this->formatPendaftaranForApi($item))
                ->values();

            return response()->json($formatted, 200);
        }

        return Inertia::render('Input/InputSmk', [
            'pendaftaranPlps' => $pendaftaranPlp,
            'smks' => $smks,
        ]);
    }

    public function indexAll()
    {
        $pendaftaranPlp = PendaftaranPlp::with([
            'user',
            'pilihanSmk1',
            'pilihanSmk2',
            'keminatan',
            'penempatanSmk',
        ])->latest()->get();
        $smk = Smk::orderBy('name')->orderBy('name', 'asc')->get(['id', 'name']);
        $dospem = User::where('role', 'Dosen Pembimbing')->orderBy('name', 'asc')->get();
        $guru = User::where('role', 'Guru')->orderBy('name', 'asc')->get();

        if (request()->wantsJson()) {
            $formatted = $pendaftaranPlp
                ->map(fn($item) => $this->formatPendaftaranForApi($item))
                ->values();

            return response()->json($formatted, 200);
        }

        return Inertia::render('PembagianPlp', ['pendaftaranPlp' => $pendaftaranPlp, 'smk' => $smk, 'dospem' => $dospem, 'guru' => $guru]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    { {
            $request->validate([
                'keminatan_id' => 'required|exists:keminatans,id',
                'nilai_plp_1' => 'required|in:A,B+,B,C+,C,D,E,Belum',
                'nilai_micro_teaching' => 'required|in:A,B+,B,C+,C,D,E,Belum',
                'pilihan_smk_1' => 'required|exists:smks,id',
                'pilihan_smk_2' => 'required|exists:smks,id',
            ]);

            $pendaftaranPlp = PendaftaranPlp::create([
                'user_id' => Auth::id(),
                'keminatan_id' => $request->keminatan_id,
                'nilai_plp_1' => $request->nilai_plp_1,
                'nilai_micro_teaching' => $request->nilai_micro_teaching,
                'pilihan_smk_1' => $request->pilihan_smk_1,
                'pilihan_smk_2' => $request->pilihan_smk_2,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Pendaftaran PLP berhasil dibuat',
                    'pendaftaran_plp' => $pendaftaranPlp
                ], 201);
            }

            return redirect()->back()->with('success', 'Message');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function assign(Request $request, string $id)
    {
        $payload = $this->normalizeAssignPayload($request);

        $validatedData = validator($payload, [
            'penempatan' => 'nullable|integer|exists:smks,id',
            'dosen_pembimbing' => 'required|integer|exists:users,id',
            'guru_pamong' => 'required|integer|exists:users,id',
        ])->validate();

        $pendaftaran = PendaftaranPlp::findOrFail($id);

        DB::transaction(function () use ($pendaftaran, $validatedData) {
            $pendaftaran->update([
                'penempatan' => $validatedData['penempatan'] ?? null,
            ]);

            $mahasiswa = $pendaftaran->user;
            if ($mahasiswa) {
                $mahasiswa->update([
                    'dosen_id' => $validatedData['dosen_pembimbing'],
                    'guru_id' => $validatedData['guru_pamong'],
                ]);
            }
        });

        $updated = $pendaftaran->fresh([
            'user',
            'pilihanSmk1',
            'pilihanSmk2',
            'keminatan',
            'penempatanSmk',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Berhasil meng-assign penempatan, dosen pembimbing, dan guru pamong.',
                'data' => $this->formatPendaftaranForApi($updated),
            ], 200);
        }

        return back()->with('success', 'Berhasil memperbarui penempatan, dosen pembimbing, dan guru pamong.');
    }


    public function assignBatch(Request $request)
    {
        $validated = $request->validate([
            'pendaftarans' => 'required|array',
            'pendaftarans.*.id' => 'required|exists:pendaftaran_plps,id',
            'pendaftarans.*.penempatan' => 'nullable|exists:smks,id',
            'pendaftarans.*.dosen_pembimbing' => 'nullable|exists:users,id',
            'pendaftarans.*.guru_pamong' => 'nullable|exists:users,id',
        ]);

        $updated = [];

        DB::transaction(function () use ($validated, &$updated) {
            foreach ($validated['pendaftarans'] as $data) {
                $pendaftaran = PendaftaranPlp::find($data['id']);
                if ($pendaftaran) {
                    $mahasiswa = $pendaftaran->user;

                    // menghandle empty string menjadi null saat di update
                    $penempatan = ($data['penempatan'] ?? '') !== '' ? $data['penempatan'] : null;
                    $dosenPembimbing = ($data['dosen_pembimbing'] ?? '') !== '' ? $data['dosen_pembimbing'] : null;
                    $guruPamong = ($data['guru_pamong'] ?? '') !== '' ? $data['guru_pamong'] : null;

                    $pendaftaran->update([
                        'penempatan' => $penempatan,
                    ]);

                    $mahasiswa->update([
                        'dosen_id' => $dosenPembimbing,
                        'guru_id' => $guruPamong,
                    ]);

                    $updated[] = $pendaftaran;
                }
            }
        });

        return back()->with('success', 'Data pada database telah berhasil diperbarui.');
    }

    private function normalizeAssignPayload(Request $request): array
    {
        return [
            'penempatan' => $this->toNullableInt(
                $request->input('penempatan', $request->input('penempatan_id', $request->input('id_smk')))
            ),
            'dosen_pembimbing' => $this->toNullableInt(
                $request->input(
                    'dosen_pembimbing',
                    $request->input(
                        'dosen_pembimbing_id',
                        $request->input('id_dosen_pembimbing', $request->input('id_dospem'))
                    )
                )
            ),
            'guru_pamong' => $this->toNullableInt(
                $request->input(
                    'guru_pamong',
                    $request->input(
                        'guru_pamong_id',
                        $request->input('id_guru_pamong', $request->input('id_pamong'))
                    )
                )
            ),
        ];
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function formatPendaftaranForApi(PendaftaranPlp $pendaftaran): array
    {
        $data = $pendaftaran->toArray();

        $dosenId = $this->toNullableInt($pendaftaran->user?->dosen_id);
        $guruId = $this->toNullableInt($pendaftaran->user?->guru_id);

        $data['dosen_pembimbing'] = $dosenId;
        $data['guru_pamong'] = $guruId;
        $data['dosen_pembimbing_id'] = $dosenId;
        $data['guru_pamong_id'] = $guruId;

        return $data;
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
