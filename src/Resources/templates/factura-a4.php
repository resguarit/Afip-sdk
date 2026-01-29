<?php
/**
 * Factura A4 - formato oficial completo
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $otros_tributos, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $condicion_venta,
 *            $periodo_desde, $periodo_hasta, $fecha_vto_pago (opcionales; si no, se usa comprobante.fecha)
 *            $footer_text, $footer_logo_src (opcionales, para pie de página)
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
$fecha = $comprobante['fecha'] ?? '';
$periodo_desde = $periodo_desde ?? $fecha;
$periodo_hasta = $periodo_hasta ?? $fecha;
$fecha_vto_pago = $fecha_vto_pago ?? $fecha;
$footer_text = $footer_text ?? '';
$footer_logo_src = $footer_logo_src ?? '';
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Factura</title>
	<style type="text/css">
		* {
			box-sizing: border-box;
			-webkit-user-select: none;
			-moz-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}
		.bill-container {
			width: 750px;
			position: absolute;
			left: 0;
			right: 0;
			margin: auto;
			border-collapse: collapse;
			font-family: sans-serif;
			font-size: 13px;
		}
		.bill-emitter-row td {
			width: 50%;
			border-bottom: 1px solid;
			padding-top: 10px;
			padding-left: 10px;
			vertical-align: top;
		}
		.bill-emitter-row {
			position: relative;
		}
		.bill-emitter-row td:nth-child(2) {
			padding-left: 60px;
		}
		.bill-emitter-row td:nth-child(1) {
			padding-right: 60px;
		}
		.bill-type {
			border: 1px solid;
			border-top: 1px solid;
			border-bottom: 1px solid;
			margin-right: -30px;
			background: white;
			width: 60px;
			height: 50px;
			position: absolute;
			left: 0;
			right: 0;
			top: -1px;
			margin: auto;
			text-align: center;
			font-size: 40px;
			font-weight: 600;
		}
		.text-lg {
			font-size: 30px;
		}
		.text-center {
			text-align: center;
		}
		.col-2 {
			width: 16.66666667%;
			float: left;
		}
		.col-3 {
			width: 25%;
			float: left;
		}
		.col-4 {
			width: 33.3333333%;
			float: left;
		}
		.col-5 {
			width: 41.66666667%;
			float: left;
		}
		.col-6 {
			width: 50%;
			float: left;
		}
		.col-8 {
			width: 66.66666667%;
			float: left;
		}
		.col-10 {
			width: 83.33333333%;
			float: left;
		}
		.row {
			overflow: hidden;
		}
		.margin-b-0 {
			margin-bottom: 0px;
		}
		.bill-row td {
			padding-top: 5px;
		}
		.bill-row td > div {
			border-top: 1px solid;
			border-bottom: 1px solid;
			margin: 0 -1px 0 -2px;
			padding: 0 10px 13px 10px;
		}
		.row-details table {
			border-collapse: collapse;
			width: 100%;
		}
		.row-details td > div,
		.row-qrcode td > div {
			border: 0;
			margin: 0 -1px 0 -2px;
			padding: 0;
		}
		.row-details table td {
			padding: 5px;
		}
		.row-details table tr:nth-child(1) {
			border-top: 1px solid;
			border-bottom: 1px solid;
			background: #c0c0c0;
			font-weight: bold;
			text-align: center;
		}
		.row-details table tr + tr {
			border-top: 1px solid #c0c0c0;
		}
		.text-right {
			text-align: right;
		}
		.margin-b-10 {
			margin-bottom: 10px;
		}
		.total-row td > div {
			border-width: 2px;
		}
		.row-qrcode td {
			padding: 10px;
		}
		#qrcode {
			width: 50%;
		}
	</style>
</head>
<body>
	<table class="bill-container">
		<tr class="bill-emitter-row">
			<td>
				<div class="bill-type"><?= htmlspecialchars($tipo_letra) ?></div>
				<div class="text-lg text-center"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
				<p><strong>Razón social:</strong> <?= htmlspecialchars($issuer['razon_social'] ?? '') ?></p>
				<p><strong>Domicilio Comercial:</strong> <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
				<p><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($issuer['condicion_iva'] ?? 'Responsable inscripto') ?></p>
			</td>
			<td>
				<div>
					<div class="text-lg">Factura</div>
					<div class="row">
						<p class="col-6 margin-b-0"><strong>Punto de Venta: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></strong></p>
						<p class="col-6 margin-b-0"><strong>Comp. Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?></strong></p>
					</div>
					<p><strong>Fecha de Emisión:</strong> <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></p>
					<p><strong>CUIT:</strong> <?= htmlspecialchars($issuer['cuit'] ?? '') ?></p>
					<?php if (!empty($issuer['iibb'])): ?><p><strong>Ingresos Brutos:</strong> <?= htmlspecialchars($issuer['iibb']) ?></p><?php endif; ?>
					<?php if (!empty($issuer['inicio_actividad'])): ?><p><strong>Fecha de Inicio de Actividades:</strong> <?= htmlspecialchars($issuer['inicio_actividad']) ?></p><?php endif; ?>
				</div>
			</td>
		</tr>
		<tr class="bill-row">
			<td colspan="2">
				<div class="row">
					<p class="col-4 margin-b-0"><strong>Período Facturado Desde: </strong><?= htmlspecialchars($periodo_desde) ?></p>
					<p class="col-3 margin-b-0"><strong>Hasta: </strong><?= htmlspecialchars($periodo_hasta) ?></p>
					<p class="col-5 margin-b-0"><strong>Fecha de Vto. para el pago: </strong><?= htmlspecialchars($fecha_vto_pago) ?></p>
				</div>
			</td>
		</tr>
		<tr class="bill-row">
			<td colspan="2">
				<div>
					<div class="row">
						<p class="col-4 margin-b-0"><strong>CUIL/CUIT: </strong><?= htmlspecialchars($receiver['nro_doc'] ?? '0') ?></p>
						<p class="col-8 margin-b-0"><strong>Apellido y Nombre / Razón social: </strong><?= htmlspecialchars($receiver['nombre'] ?? 'Consumidor Final') ?></p>
					</div>
					<div class="row">
						<p class="col-6 margin-b-0"><strong>Condición Frente al IVA: </strong><?= htmlspecialchars($receiver['condicion_iva'] ?? 'Consumidor final') ?></p>
						<p class="col-6 margin-b-0"><strong>Domicilio: </strong><?= htmlspecialchars($receiver['domicilio'] ?? '-') ?></p>
					</div>
					<p><strong>Condicion de venta: </strong><?= htmlspecialchars($condicion_venta) ?></p>
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
						<?php foreach ($items as $item):
							$cant = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
							$pu = (float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
							$st = isset($item['subtotal']) ? (float) $item['subtotal'] : ($cant * $pu);
						?>
						<tr>
							<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
							<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
							<td><?= number_format($cant, 2, ',', '.') ?></td>
							<td><?= htmlspecialchars($item['unit'] ?? 'Unidad') ?></td>
							<td><?= number_format($pu, 2, ',', '.') ?></td>
							<td>0,00</td>
							<td>0,00</td>
							<td><?= number_format($st, 2, ',', '.') ?></td>
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
				<div>
					<div class="row">
						<?php if ($qr_src !== ''): ?>
						<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" />
						<?php endif; ?>
					</div>
				</div>
			</td>
			<td>
				<div>
					<div class="row text-right margin-b-10">
						<strong>CAE Nº:&nbsp;</strong> <?= htmlspecialchars($cae) ?>
					</div>
					<div class="row text-right">
						<strong>Fecha de Vto. de CAE:&nbsp;</strong> <?= htmlspecialchars($cae_vencimiento) ?>
					</div>
				</div>
			</td>
		</tr>
		<?php if ($footer_text !== '' || $footer_logo_src !== ''): ?>
		<tr class="bill-row row-details">
			<td colspan="2">
				<div>
					<div class="row text-center margin-b-10">
						<?php if ($footer_text !== ''): ?>
						<span style="vertical-align: bottom;"><?= htmlspecialchars($footer_text) ?></span>
						<?php endif; ?>
						<?php if ($footer_logo_src !== ''): ?>
						<img style="height: 20px; vertical-align: middle;" src="<?= htmlspecialchars($footer_logo_src) ?>" alt="" />
						<?php endif; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php endif; ?>
	</table>
</body>
</html>
