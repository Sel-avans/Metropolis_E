document.addEventListener(`DOMContentLoaded`,()=>{let e=document.querySelectorAll(`.grid-cell`),t=document.querySelectorAll(`.library-item`),n=null,r=!1,i=null;async function a(e,t,r,i){try{await fetch(`/grid/update`,{method:`POST`,headers:{"Content-Type":`application/json`,"X-CSRF-TOKEN":document.querySelector(`meta[name="csrf-token"]`).content},body:JSON.stringify({old_row:e,old_col:t,new_row:r,new_col:i,function_id:n.id})}),o()}catch(e){console.error(`Fout bij opslaan gridcel:`,e)}}async function o(){try{let e=document.getElementById(`qol-score-value`),t=document.getElementById(`breakdown-qol-score`);if(!e&&!t)return;let n=await(await fetch(`/qol/details`)).json();e&&(e.textContent=n.total_score),t&&(t.innerHTML=s(n))}catch(e){console.error(`Fout bij ophalen QoL:`,e)}}function s(e){let t=``;t+=`<h1 class="dark:text-teal-500">Breakdown QoL Score</h1>`;for(let[n,r]of Object.entries(e.categories))t+=`
                <h3 class="font-semibold mt-3 dark:text-teal-600">
                    ${n} (totaal: ${r.total})
                </h3>
            `,r.items.forEach(e=>{t+=`
                    <div class="flex justify-between text-gray-700 dark:text-white">
                        <span>${e.function}</span>
                        <span class="${e.value<=0?`text-red-600`:`text-green-600`}">
                            ${e.value}
                        </span>
                    </div>
                `});return t+=`
            <h3 class="font-bold mt-4 dark:text-teal-600">
                Totale QoL: ${e.total_score}
            </h3>
        `,t}t.forEach(e=>{e.addEventListener(`dragstart`,t=>{r=!0,n={id:Number(e.dataset.functionId),name:e.dataset.functionName,image:e.dataset.image},t.dataTransfer.setDragImage(e.querySelector(`img`),16,16)})}),e.forEach(e=>{e.addEventListener(`dragstart`,t=>{let a=e.querySelector(`.grid-function-icon`);a&&(r=!0,n={id:Number(a.dataset.functionId),name:a.alt,image:a.src},t.dataTransfer.setDragImage(a,16,16),i=e,e.classList.add(`drag-source`))})}),e.forEach(e=>{e.addEventListener(`dragover`,t=>{t.preventDefault(),e.classList.add(`drag-over`)}),e.addEventListener(`dragleave`,()=>{e.classList.remove(`drag-over`)}),e.addEventListener(`drop`,async t=>{t.preventDefault(),r=!1,e.classList.remove(`drag-over`);let o=e.dataset.row,s=e.dataset.col,c=null,l=null;i&&=(c=i.dataset.row,l=i.dataset.col,i.innerHTML=``,i.removeAttribute(`draggable`),i.classList.remove(`drag-source`),null),e.innerHTML=``;let u=document.createElement(`img`);u.src=n.image,u.alt=n.name,u.dataset.functionId=n.id,u.classList.add(`grid-function-icon`,`object-contain`),e.appendChild(u),e.setAttribute(`draggable`,`true`),await a(c,l,o,s)})}),e.forEach(t=>{t.setAttribute(`tabindex`,`0`),t.addEventListener(`click`,()=>{r||(e.forEach(e=>e.classList.remove(`selected`)),t.classList.add(`selected`))}),t.addEventListener(`keydown`,n=>{r||(n.key===`Enter`||n.key===` `)&&(e.forEach(e=>e.classList.remove(`selected`)),t.classList.add(`selected`))})}),o()});