// Shared by talks.html and speaking.html. "Generate Template" turns the form into a table
// (one row per field, headed by that field's <label> text) for the requester to copy into an email.
// If the form has data-table-heading, that text becomes a title row at the top of the table.
(function() {
  const form = document.getElementById('sr-form');
  const output = document.getElementById('templateHtml');
  const copyButton = document.getElementById('copyTemplate');

  function escapeHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

  function makeHtmlTable(){
    const heading = form.dataset.tableHeading;
    let rows = heading ? '<tr><th colspan="2" style="text-align:left;padding:8px;border:1px solid #ddd;background:#f5f5f5">' + escapeHtml(heading) + '</th></tr>' : '';
    form.querySelectorAll('input, select, textarea').forEach(field => {
      const label = form.querySelector(`label[for="${field.id}"]`).textContent;
      rows += `<tr><th style="text-align:left;padding:6px;border:1px solid #ddd">${escapeHtml(label)}</th><td style="padding:6px;border:1px solid #ddd">${escapeHtml(field.value)}</td></tr>`;
    });
    return `<table style="border-collapse:collapse;max-width:100%">${rows}</table>`;
  }

  async function copyGeneratedTable(){
    const table = output.querySelector('table');
    if (!table) return;

    const html = table.outerHTML;
    const text = table.innerText || table.textContent || '';

    if (navigator.clipboard && window.ClipboardItem){
      const item = new ClipboardItem({
        'text/html': new Blob([html], { type: 'text/html' }),
        'text/plain': new Blob([text], { type: 'text/plain' })
      });
      await navigator.clipboard.write([item]);
      return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText){
      await navigator.clipboard.writeText(text);
      return;
    }

    const temp = document.createElement('textarea');
    temp.value = text;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    temp.remove();
  }

  document.getElementById('generate').addEventListener('click', ()=>{
    output.innerHTML = makeHtmlTable();
    copyButton.style.display = 'inline-block';
  });

  copyButton.addEventListener('click', async ()=>{
    const originalText = copyButton.textContent;
    try {
      await copyGeneratedTable();
      copyButton.textContent = 'Copied';
    } catch (err) {
      copyButton.textContent = 'Copy failed';
    }
    setTimeout(()=>{ copyButton.textContent = originalText; }, 1500);
  });
})();
