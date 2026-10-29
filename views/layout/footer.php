<?php
/**
 * Общий подвал: модальное окно подтверждения, уведомления, скрипты.
 * $pageScript — имя файла из assets/js/pages, $pageData — данные для JS этой страницы.
 */
$appData = array_merge([
    'csrf'     => csrf_token(),
    'currency' => config('app')['currency'],
    'shopName' => config('app')['shop_name'],
], $pageData ?? []);
?>
        </div>
    </main>
</div>

<!-- Окно подтверждения действий -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <i class="bi bi-question-circle text-warning display-6"></i>
                <p class="mt-3 mb-0" id="confirmText"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmOk">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>

<script>window.APP = <?= json_encode($appData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?php if (!empty($needCharts)): ?>
<script src="assets/vendor/chartjs/chart.umd.min.js"></script>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="assets/js/pages/<?= e($pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
