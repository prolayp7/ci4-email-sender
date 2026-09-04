<?php
/**
 * Shared field markup for the recipient create/edit form, used by both
 * the standalone form page and the Add Recipient modal. Each field's
 * .invalid-feedback div is always rendered (even empty) so client-side
 * JS can populate it on an AJAX validation error without creating DOM
 * nodes on the fly — Bootstrap only shows it once .is-invalid is set.
 *
 * @var array|null $recipient
 * @var array|null $errors
 */
$recipient ??= null;
$errors ??= [];
$val = static fn (string $field) => esc($recipient[$field] ?? old($field) ?? '');
?>
<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" data-field="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= $val('name') ?>" required maxlength="150">
    <div class="invalid-feedback" data-field-error="name"><?= esc($errors['name'] ?? '') ?></div>
</div>
<div class="mb-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" data-field="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= $val('email') ?>" required maxlength="191">
    <div class="invalid-feedback" data-field-error="email"><?= esc($errors['email'] ?? '') ?></div>
</div>
<div class="mb-3">
    <label class="form-label">Company</label>
    <input type="text" name="company" data-field="company" class="form-control <?= isset($errors['company']) ? 'is-invalid' : '' ?>" value="<?= $val('company') ?>" maxlength="150">
    <div class="invalid-feedback" data-field-error="company"><?= esc($errors['company'] ?? '') ?></div>
</div>
<div class="mb-3">
    <label class="form-label">Phone</label>
    <input type="text" name="phone" data-field="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= $val('phone') ?>" maxlength="30">
    <div class="invalid-feedback" data-field-error="phone"><?= esc($errors['phone'] ?? '') ?></div>
</div>
<div class="mb-3">
    <label class="form-label">Notes</label>
    <textarea name="notes" data-field="notes" class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" rows="3" maxlength="2000"><?= $val('notes') ?></textarea>
    <div class="invalid-feedback" data-field-error="notes"><?= esc($errors['notes'] ?? '') ?></div>
</div>
