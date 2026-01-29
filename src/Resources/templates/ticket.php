<?php
/**
 * Ticket fiscal - formato térmico 80mm
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $tipo_codigo, $condicion_venta
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$tipo_codigo = $tipo_codigo ?? 6;
$condicion_venta = $condicion_venta ?? 'Efectivo';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Ticket</title>
	<style type="text/css">
		/* CSS EXACTO - NO TOCAR */
		@page {
			margin: 0;
			size: 80mm auto;
		}

		body {
			font-family: 'DejaVu Sans', sans-serif;
			font-size: 9px;
			margin: 0;
			padding: 0;
			color: #000;
		}

		.ticket-wrapper {
			/* MARGEN IZQUIERDO 10mm (Para salvar el corte de impresora) */
			margin-left: 10mm;

			/* ANCHO 60mm (Para asegurar que entre todo a la derecha) */
			width: 60mm;
			max-width: 60mm;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		td {
			padding: 1px 0;
			vertical-align: top;
			white-space: normal;
			word-wrap: break-word;
			overflow: visible;
		}

		.center {
			text-align: center;
		}

		.right {
			text-align: right;
		}

		.left {
			text-align: left;
		}

		.bold {
			font-weight: bold;
		}

		.mono {
			font-family: 'DejaVu Sans Mono', monospace;
			font-size: 9px;
		}

		.header-title {
			font-size: 11px;
			font-weight: bold;
			text-transform: uppercase;
			text-align: center;
		}

		.header-info {
			font-size: 8px;
			text-align: center;
		}

		.divider {
			border-bottom: 1px dashed #000;
			margin: 3px 0;
			width: 100%;
			display: block;
		}

		.prod-desc {
			font-size: 9px;
			font-weight: bold;
			padding-top: 3px;
			display: block;
			width: 100%;
		}

		.total-row {
			font-size: 12px;
			font-weight: bold;
			padding-top: 5px;
		}

		#qrcode {
			width: 75%;
		}
	</style>
</head>
<body>
	<div class="ticket-wrapper">
		<table>
			<tr>
				<td class="header-info center">
					<span class="bold"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></span><br>
					<?= htmlspecialchars($issuer['domicilio'] ?? '') ?><br>
					C.U.I.T.: <?= htmlspecialchars($issuer['cuit'] ?? '') ?><br>
					<?= htmlspecialchars(strtoupper($issuer['condicion_iva'] ?? 'RESPONSABLE INSCRIPTO')) ?>

					<?php if (!empty($issuer['iibb'])): ?>
					<br>IIBB: <?= htmlspecialchars($issuer['iibb']) ?>
					<?php endif; ?>

					<?php if (!empty($issuer['inicio_actividad'])): ?>
					<br>Inicio de actividad: <?= htmlspecialchars($issuer['inicio_actividad']) ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<span class="divider"></span>

		<p class="header-title">FACTURA <?= htmlspecialchars($tipo_letra) ?></p>
		<p class="header-info center">
			Codigo <?= (int) $tipo_codigo ?><br>
			P.V: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?> |
			Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?><br>
			Fecha: <?= htmlspecialchars($comprobante['fecha'] ?? '') ?><br>
			Concepto: <?= htmlspecialchars($comprobante['concepto_texto'] ?? 'Productos') ?>
		</p>

		<span class="divider"></span>

		<p class="header-info center">A CONSUMIDOR FINAL</p>

		<span class="divider"></span>

		<table>
			<?php foreach ($items as $item): ?>
			<tr>
				<td class="left"><?= (int) ($item['quantity'] ?? $item['cantidad'] ?? 1) ?></td>
				<td class="left"><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
				<td class="right"><?= htmlspecialchars($item['taxRate'] ?? $item['iva_pct'] ?? '21') ?>%</td>
				<td class="right mono"><?= number_format((float) ($item['unitPrice'] ?? $item['subtotal'] ?? 0), 2, ',', '.') ?></td>
			</tr>
			<?php endforeach; ?>
		</table>

		<span class="divider"></span>

		<table>
			<tr>
				<td class="left total-row">TOTAL</td>
				<td class="right total-row"><?= number_format((float) $total, 2, ',', '.') ?></td>
			</tr>
		</table>

		<span class="divider"></span>

		<p class="header-info center">
			CAE: <?= htmlspecialchars($cae) ?><br>
			Vto: <?= htmlspecialchars($cae_vencimiento) ?>
		</p>

		<?php if ($qr_src !== ''): ?>
		<p class="center">
			<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" />
		</p>
		<?php endif; ?>
	</div>
</body>
</html>
