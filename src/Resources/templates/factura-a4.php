<?php
/**
 * Factura A4 - formato oficial completo
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $otros_tributos, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $condicion_venta, $logo_src (opcional)
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$otros_tributos = $otros_tributos ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$condicion_venta = $condicion_venta ?? 'Efectivo';
$logo_src = $logo_src ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Factura</title>
	<style type="text/css">
		body {
			font-family: Arial, sans-serif;
			font-size: 12px;
			margin: 20px;
		}

		.header {
			border-bottom: 2px solid #333;
			margin-bottom: 20px;
			padding-bottom: 10px;
		}

		.header-table {
			width: 100%;
		}

		.logo-cell {
			width: 180px;
			vertical-align: top;
			padding-right: 20px;
		}

		.logo {
			max-width: 120px;
			max-height: 80px;
			width: auto;
			height: auto;
			object-fit: contain;
		}

		.company-data {
			font-size: 10px;
		}

		.company-name {
			font-weight: bold;
			font-size: 14px;
			margin-bottom: 5px;
		}

		.header-info {
			vertical-align: top;
		}

		.branch {
			font-size: 18px;
			font-weight: bold;
			margin-bottom: 5px;
		}

		.info-table,
		.items-table,
		.totals-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 15px;
		}

		.info-table td {
			padding: 5px 8px;
			font-size: 14px;
		}

		.items-table th,
		.items-table td {
			border: 1px solid #333;
			padding: 8px;
			text-align: left;
			font-size: 14px;
		}

		.items-table th {
			background: #f5f5f5;
			font-weight: bold;
		}

		.totals-table td {
			padding: 5px 8px;
			font-size: 16px;
		}

		.right {
			text-align: right;
		}

		.bold {
			font-weight: bold;
		}

		.mt-2 {
			margin-top: 10px;
		}

		#qrcode {
			max-width: 120px;
			height: auto;
		}
	</style>
</head>
<body>
	<div class="header">
		<table class="header-table">
			<tr>
				<td class="logo-cell">
					<?php if ($logo_src !== ''): ?>
					<img class="logo" src="<?= htmlspecialchars($logo_src) ?>" alt="Logo" />
					<?php endif; ?>
					<div class="company-data">
						<div class="company-name"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
						<p>Domicilio: <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
						<p>CUIT: <?= htmlspecialchars($issuer['cuit'] ?? '') ?></p>
						<p><?= htmlspecialchars($issuer['condicion_iva'] ?? 'Responsable Inscripto') ?></p>
						<?php if (!empty($issuer['iibb'])): ?><p>IIBB: <?= htmlspecialchars($issuer['iibb']) ?></p><?php endif; ?>
						<?php if (!empty($issuer['inicio_actividad'])): ?><p>Inicio de actividad: <?= htmlspecialchars($issuer['inicio_actividad']) ?></p><?php endif; ?>
					</div>
				</td>
				<td class="header-info">
					<div class="branch">Factura <?= htmlspecialchars($tipo_letra) ?></div>
					<p>Punto de Venta: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></p>
					<p>Comp. Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?></p>
					<p>Fecha de Emisión: <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
					<p>Concepto: <?= htmlspecialchars($comprobante['concepto_texto'] ?? 'Productos') ?></p>
				</td>
			</tr>
		</table>
	</div>

	<table class="info-table">
		<tr>
			<td><span class="bold">Cliente:</span></td>
			<td><?= htmlspecialchars($receiver['nombre'] ?? 'Consumidor Final') ?></td>
		</tr>
		<tr>
			<td><span class="bold">CUIT/CUIL/DNI:</span></td>
			<td><?= htmlspecialchars($receiver['nro_doc'] ?? '0') ?></td>
		</tr>
		<tr>
			<td><span class="bold">Condición IVA:</span></td>
			<td><?= htmlspecialchars($receiver['condicion_iva'] ?? 'Consumidor final') ?></td>
		</tr>
		<?php if (!empty($receiver['domicilio'])): ?>
		<tr>
			<td><span class="bold">Domicilio:</span></td>
			<td><?= htmlspecialchars($receiver['domicilio']) ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td><span class="bold">Condición de venta:</span></td>
			<td><?= htmlspecialchars($condicion_venta) ?></td>
		</tr>
	</table>

	<table class="items-table">
		<thead>
			<tr>
				<th>Código</th>
				<th>Producto / Servicio</th>
				<th>Cantidad</th>
				<th>U. Medida</th>
				<th>Precio Unit.</th>
				<th class="right">Subtotal</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($items as $item): ?>
			<tr>
				<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
				<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
				<td><?= number_format((float) ($item['quantity'] ?? $item['cantidad'] ?? 1), 2, ',', '.') ?></td>
				<td><?= htmlspecialchars($item['unit'] ?? 'Unidad') ?></td>
				<td><?= number_format((float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0), 2, ',', '.') ?></td>
				<td class="right"><?= number_format((float) ($item['subtotal'] ?? ($item['quantity'] ?? 1) * ($item['unitPrice'] ?? 0)), 2, ',', '.') ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="totals-table">
		<tr>
			<td class="right bold">Subtotal:</td>
			<td class="right"><?= number_format((float) $subtotal, 2, ',', '.') ?></td>
		</tr>
		<?php if ((float) $iva_total > 0): ?>
		<tr>
			<td class="right bold">IVA:</td>
			<td class="right"><?= number_format((float) $iva_total, 2, ',', '.') ?></td>
		</tr>
		<?php endif; ?>
		<?php if ((float) $otros_tributos > 0): ?>
		<tr>
			<td class="right bold">Importe Otros Tributos:</td>
			<td class="right"><?= number_format((float) $otros_tributos, 2, ',', '.') ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<td class="right bold">Importe total:</td>
			<td class="right bold"><?= number_format((float) $total, 2, ',', '.') ?></td>
		</tr>
	</table>

	<div class="mt-2">
		<?php if ($qr_src !== ''): ?>
		<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" />
		<?php endif; ?>
		<div class="right">
			<p><span class="bold">CAE Nº:</span> <?= htmlspecialchars($cae) ?></p>
			<p><span class="bold">Fecha de Vto. de CAE:</span> <?= htmlspecialchars($cae_vencimiento) ?></p>
		</div>
	</div>
</body>
</html>
