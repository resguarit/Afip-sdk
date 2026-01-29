<?php
/**
 * Factura A4 - formato oficial completo
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $otros_tributos, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $condicion_venta
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
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Factura</title>
	<style type="text/css">
		* { box-sizing: border-box; }
		.bill-container { width: 750px; margin: auto; border-collapse: collapse; font-family: sans-serif; font-size: 13px; }
		.bill-emitter-row td { width: 50%; border-bottom: 1px solid; padding: 10px; vertical-align: top; }
		.bill-type { border: 1px solid; width: 60px; height: 50px; margin: auto; text-align: center; font-size: 40px; font-weight: 600; }
		.text-lg { font-size: 30px; }
		.text-center { text-align: center; }
		.text-right { text-align: right; }
		.col-2 { width: 16.67%; float: left; }
		.col-10 { width: 83.33%; float: left; }
		.row { overflow: hidden; }
		.margin-b-0 { margin-bottom: 0; }
		.bill-row td > div { border-top: 1px solid; border-bottom: 1px solid; padding: 0 10px 13px; }
		.row-details table { border-collapse: collapse; width: 100%; }
		.row-details table tr:nth-child(1) { border-top: 1px solid; border-bottom: 1px solid; background: #c0c0c0; font-weight: bold; text-align: center; }
		.row-details table tr + tr { border-top: 1px solid #c0c0c0; }
		.row-details table td { padding: 5px; }
		.total-row td > div { border-width: 2px; }
		#qrcode { width: 50%; }
	</style>
</head>
<body>
	<table class="bill-container">
		<tr class="bill-emitter-row">
			<td>
				<div class="bill-type text-center"><?= htmlspecialchars($tipo_letra) ?></div>
				<div class="text-lg text-center"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
				<p><strong>Razón social:</strong> <?= htmlspecialchars($issuer['razon_social'] ?? '') ?></p>
				<p><strong>Domicilio Comercial:</strong> <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
				<p><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($issuer['condicion_iva'] ?? '') ?></p>
			</td>
			<td>
				<div class="text-lg">Factura</div>
				<p><strong>Punto de Venta:</strong> <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></p>
				<p><strong>Comp. Nro:</strong> <?= htmlspecialchars($comprobante['nro'] ?? '') ?></p>
				<p><strong>Fecha de Emisión:</strong> <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
				<p><strong>CUIT:</strong> <?= htmlspecialchars($issuer['cuit'] ?? '') ?></p>
				<?php if (!empty($issuer['iibb'])): ?><p><strong>Ingresos Brutos:</strong> <?= htmlspecialchars($issuer['iibb']) ?></p><?php endif; ?>
				<?php if (!empty($issuer['inicio_actividad'])): ?><p><strong>Fecha de Inicio de Actividades:</strong> <?= htmlspecialchars($issuer['inicio_actividad']) ?></p><?php endif; ?>
			</td>
		</tr>
		<tr class="bill-row">
			<td colspan="2">
				<div class="row">
					<p class="col-10 margin-b-0"><strong>Período Facturado:</strong> <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
					<p class="col-10 margin-b-0"><strong>Fecha de Vto. para el pago:</strong> <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
				</div>
			</td>
		</tr>
		<tr class="bill-row">
			<td colspan="2">
				<div>
					<p><strong>CUIL/CUIT:</strong> <?= htmlspecialchars($receiver['nro_doc'] ?? $issuer['cuit'] ?? '') ?></p>
					<p><strong>Apellido y Nombre / Razón social:</strong> <?= htmlspecialchars($receiver['nombre'] ?? '') ?></p>
					<p><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($receiver['condicion_iva'] ?? '') ?></p>
					<?php if (!empty($receiver['domicilio'])): ?><p><strong>Domicilio:</strong> <?= htmlspecialchars($receiver['domicilio']) ?></p><?php endif; ?>
					<p><strong>Condicion de venta:</strong> <?= htmlspecialchars($condicion_venta) ?></p>
				</div>
			</td>
		</tr>
		<tr class="bill-row row-details">
			<td colspan="2">
				<div>
					<table>
						<tr>
							<td>Código</td>
							<td>Producto / Servicio</td>
							<td>Cantidad</td>
							<td>U. Medida</td>
							<td>Precio Unit.</td>
							<td>% Bonif.</td>
							<td>Imp. Bonif.</td>
							<td>Subtotal</td>
						</tr>
						<?php foreach ($items as $item): ?>
						<tr>
							<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
							<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
							<td><?= number_format((float) ($item['quantity'] ?? $item['cantidad'] ?? 1), 2, ',', '.') ?></td>
							<td><?= htmlspecialchars($item['unit'] ?? 'Unidad') ?></td>
							<td><?= number_format((float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0), 2, ',', '.') ?></td>
							<td>0,00</td>
							<td>0,00</td>
							<td><?= number_format((float) ($item['subtotal'] ?? $item['unitPrice'] ?? 0), 2, ',', '.') ?></td>
						</tr>
						<?php endforeach; ?>
					</table>
				</div>
			</td>
		</tr>
		<tr class="bill-row total-row">
			<td colspan="2">
				<div>
					<div class="row text-right">
						<p class="col-10 margin-b-0"><strong>Subtotal: $</strong></p>
						<p class="col-2 margin-b-0"><strong><?= number_format((float) $subtotal, 2, ',', '.') ?></strong></p>
					</div>
					<div class="row text-right">
						<p class="col-10 margin-b-0"><strong>Importe Otros Tributos: $</strong></p>
						<p class="col-2 margin-b-0"><strong><?= number_format((float) $otros_tributos, 2, ',', '.') ?></strong></p>
					</div>
					<div class="row text-right">
						<p class="col-10 margin-b-0"><strong>Importe total: $</strong></p>
						<p class="col-2 margin-b-0"><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></p>
					</div>
				</div>
			</td>
		</tr>
		<tr class="bill-row row-details">
			<td>
				<?php if ($qr_src !== ''): ?>
				<div><img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" /></div>
				<?php endif; ?>
			</td>
			<td>
				<div class="text-right">
					<p><strong>CAE Nº:</strong> <?= htmlspecialchars($cae) ?></p>
					<p><strong>Fecha de Vto. de CAE:</strong> <?= htmlspecialchars($cae_vencimiento) ?></p>
				</div>
			</td>
		</tr>
	</table>
</body>
</html>
