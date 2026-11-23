(function () {
  const avatars = [
    { src: '/assets/images/avatars/user-admin.svg', name: 'Nhà thám hiểm xanh' },
    { src: '/assets/images/avatars/user-demo.svg', name: 'Nhà thám hiểm hồng' },
    { src: '/assets/images/avatars/user-hoangnam.svg', name: 'Nhà thám hiểm tím' },
    { src: '/assets/images/avatars/user-thuha.svg', name: 'Nhà thám hiểm cam' },
    { src: '/assets/images/avatars/user-quocbao.svg', name: 'Nhà thám hiểm vàng' },
    { src: '/assets/images/avatars/user-maianh.svg', name: 'Nhà thám hiểm xanh lá' },
  ];
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[char]));
  const assetBase = window.APP_URL || new URL('.', window.location.href).pathname.replace(/\/$/, '');
  const assetUrl = path => `${assetBase}/${String(path).replace(/^\/+/, '')}`;

  function markup({ id = 'avatar-picker', value = '' } = {}) {
    const selected = avatars.find(avatar => avatar.src === value)?.src || '';
    return `<section class="avatar-picker" id="${escapeHtml(id)}" data-avatar-picker aria-label="Chọn avatar hoạt hình">
      <div class="avatar-picker-heading"><span>Chọn avatar hoạt hình</span><button type="button" class="avatar-random-button" data-avatar-random><i class="fa-solid fa-shuffle"></i> Lấy avatar ngẫu nhiên</button></div>
      <input type="hidden" name="avatar" data-avatar-value value="${escapeHtml(selected)}">
      <div class="avatar-picker-options">
        ${avatars.map(avatar => `<button type="button" class="avatar-choice ${avatar.src === selected ? 'is-selected' : ''}" data-avatar-choice="${avatar.src}" aria-pressed="${avatar.src === selected ? 'true' : 'false'}" title="${escapeHtml(avatar.name)}"><img src="${assetUrl(avatar.src)}" alt="${escapeHtml(avatar.name)}"><span>${escapeHtml(avatar.name.replace('Nhà thám hiểm ', ''))}</span></button>`).join('')}
      </div>
    </section>`;
  }

  function setValue(picker, value) {
    if (!picker) return;
    const input = picker.querySelector('[data-avatar-value]');
    const choices = [...picker.querySelectorAll('[data-avatar-choice]')];
    const selected = choices.find(choice => choice.dataset.avatarChoice === value);
    if (input) input.value = selected ? value : '';
    choices.forEach(choice => {
      const active = choice === selected;
      choice.classList.toggle('is-selected', active);
      choice.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  document.addEventListener('click', event => {
    const choice = event.target.closest('[data-avatar-choice]');
    if (choice) {
      setValue(choice.closest('[data-avatar-picker]'), choice.dataset.avatarChoice);
      return;
    }
    const randomButton = event.target.closest('[data-avatar-random]');
    if (!randomButton) return;
    const picker = randomButton.closest('[data-avatar-picker]');
    const current = picker?.querySelector('[data-avatar-value]')?.value;
    const candidates = avatars.filter(avatar => avatar.src !== current);
    const next = candidates[Math.floor(Math.random() * candidates.length)] || avatars[0];
    setValue(picker, next.src);
  });

  window.AvatarPicker = { avatars, markup, setValue };
})();
