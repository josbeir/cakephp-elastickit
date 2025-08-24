<?php
/**
 * @var \DebugKit\View\AjaxView $this
 * @package ElasticKit
 */
//phpcs:disable
?>
<style type="text/css">
    .es-log-panel-items > li {
        padding: 1rem 0;
        border-bottom: 1px solid #ddd;
        font-size: 14px;
        display: grid;
        grid-template-columns: minmax(40px, 60px) auto;
        align-items: center;
    }

    .es-log-panel-items > li .es-request--count {
        margin-right: 1rem;
        font-size: 1rem;
        font-weight: bold;
        text-align: center;
    }

    .es-log-panel-items > li:last-child {
        border-bottom: none;
    }

    .es-log-panel-items .es-request--body {
        margin-top: 1rem;
        padding: 1rem;
        background-color: #f8f8f8;
        overflow-y: auto;
        max-height: 200px;
        border: 1px solid #ddd;
    }
    .es-log-panel-items .es-request--body pre {
        transition: background-color 0.3s ease;
        cursor: pointer;
    }
</style>
<script>
function copyToClipboard(element) {
    const text = element.innerText;
    navigator.clipboard.writeText(text).then(function() {
        element.style.backgroundColor = '#d4edda';
        setTimeout(() => { element.style.backgroundColor = ''; }, 700);
    });
}
</script>
<?php foreach ($loggers as $connectionName => $logger) : ?>
    <h4><?= sprintf('Connection name: %s', h($connectionName)) ?></h4>
    <ul class="es-log-panel-items">
        <?php foreach ($logger->requests() as $count => $request) : ?>
            <li>
                <div class="es-request--count"><?= sprintf('#%d', $count + 1) ?></div>
                <div class="es-request--context">
                    <div class="es-request--message"><?= $request['message']; ?></div>
                    <?php if ($request['body']) : ?>
                        <div class="es-request--body">
                            <pre class="es-request--body-pre" onclick="copyToClipboard(this)"><?= $request['body']; ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

