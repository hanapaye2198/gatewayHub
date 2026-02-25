<x-layouts::auth.simple>
    <div class="flex w-full max-w-sm flex-col gap-6">
        <flux:heading size="lg">Coins Dynamic QR</flux:heading>

        <form id="coins-qr-form" class="flex flex-col gap-4">
            <flux:field>
                <flux:label>Amount (PHP)</flux:label>
                <flux:input type="number" name="amount" id="amount" min="1" step="0.01" placeholder="100" required />
                <flux:error name="amount" />
            </flux:field>
            <flux:button type="submit" variant="primary" id="generate-btn">
                Generate QR
            </flux:button>
        </form>

        <div id="qr" class="min-h-[200px] flex items-center justify-center rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-950"></div>
        <p id="qr-error" class="text-sm text-red-600 dark:text-red-400 hidden"></p>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGmY3d8y8pnUuN2eY0y+W2n6+MwJzMjqBG4t/DP4bwRDnF2nEdFNPs8j/v2Bm0vS6e0E5T0m0hQ2p+A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        document.getElementById('coins-qr-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const amountInput = document.getElementById('amount');
            const qrDiv = document.getElementById('qr');
            const qrError = document.getElementById('qr-error');
            const btn = document.getElementById('generate-btn');

            qrError.classList.add('hidden');
            qrError.textContent = '';
            qrDiv.innerHTML = '';
            btn.disabled = true;

            fetch('{{ route("coins.generate-qr") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ amount: parseFloat(amountInput.value) || 0 })
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (result.ok && result.data.success && result.data.qr_code_string) {
                        new QRCode(qrDiv, {
                            text: result.data.qr_code_string,
                            width: 200,
                            height: 200
                        });
                    } else {
                        qrError.textContent = result.data.message || 'Failed to generate QR.';
                        qrError.classList.remove('hidden');
                    }
                })
                .catch(function () {
                    qrError.textContent = 'Request failed. Try again.';
                    qrError.classList.remove('hidden');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    </script>
</x-layouts::auth.simple>
