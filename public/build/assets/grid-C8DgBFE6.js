document.addEventListener(`DOMContentLoaded`,()=>{let e=document.querySelectorAll(`.grid-cell`),t=document.querySelectorAll(`.library-item`),n=null,r=!1,i=null,a;async function o(e,t,r,i){try{await fetch(`/grid/update`,{method:`POST`,headers:{"Content-Type":`application/json`,"X-CSRF-TOKEN":document.querySelector(`meta[name="csrf-token"]`).content},body:JSON.stringify({old_row:e,old_col:t,new_row:r,new_col:i,function_id:n.id})}),s()}catch(e){console.error(`Fout bij opslaan gridcel:`,e)}}async function s(){try{let e=document.getElementById(`qol-score-value`),t=document.getElementById(`breakdown-qol-score`),n=document.getElementById(`old-qol-score`);if(!e&&!t)return;let r=await(await fetch(`/qol/details`)).json();e&&(e.textContent=r.total_score,n.innerHTML=c(r)),t&&(t.innerHTML=l(r))}catch(e){console.error(`Fout bij ophalen QoL:`,e)}}function c(e){let t=``;if(a===void 0)t+=``;else{let n=e.total_score-a;t+=`   
            <span class="${n<0?`text-red-600`:`text-green-600`}">
                ${n}
            </span>
            `}return e.total_score!==0&&(a=e.total_score),t}function l(e){let t=``;t+=`<h3 class="dark:text-teal-500">Breakdown QoL Score</h3>`;for(let[n,r]of Object.entries(e.categories))t+=`
                <h3 class="font-semibold mt-3 dark:text-teal-600">
                    ${n} (total:
                    <span class="${r.total<=0?`text-red-600`:`text-green-600`}">
                        ${r.total}
                    </span>
                    )
                </h3>
            `;return t+=`
            <h3 class="font-bold mt-4 dark:text-teal-600">
                Total QoL: 
                <span class="${e.total_score<=0?`text-red-600`:`text-green-600`}">${e.total_score}</span>
            </h3>
        `,t}t.forEach(e=>{e.addEventListener(`dragstart`,t=>{r=!0,n={id:Number(e.dataset.functionId),name:e.dataset.functionName,image:e.dataset.image},t.dataTransfer.setDragImage(e.querySelector(`img`),16,16)})}),e.forEach(e=>{e.addEventListener(`dragstart`,t=>{let a=e.querySelector(`.grid-function-icon`);a&&(r=!0,n={id:Number(a.dataset.functionId),name:a.alt,image:a.src},t.dataTransfer.setDragImage(a,16,16),i=e,e.classList.add(`drag-source`))})}),e.forEach(e=>{e.addEventListener(`dragover`,t=>{t.preventDefault(),e.classList.add(`drag-over`)}),e.addEventListener(`dragleave`,()=>{e.classList.remove(`drag-over`)}),e.addEventListener(`drop`,async t=>{t.preventDefault(),r=!1,e.classList.remove(`drag-over`);let a=e.dataset.row,s=e.dataset.col,c=null,l=null;i&&=(c=i.dataset.row,l=i.dataset.col,i.innerHTML=``,i.removeAttribute(`draggable`),i.classList.remove(`drag-source`),null),e.innerHTML=``;let u=document.createElement(`img`);u.src=n.image,u.alt=n.name,u.dataset.functionId=n.id,u.classList.add(`grid-function-icon`,`object-contain`),e.appendChild(u),e.setAttribute(`draggable`,`true`),await o(c,l,a,s)})}),e.forEach(t=>{t.setAttribute(`tabindex`,`0`),t.addEventListener(`click`,()=>{r||(e.forEach(e=>e.classList.remove(`selected`)),t.classList.add(`selected`))}),t.addEventListener(`keydown`,n=>{r||(n.key===`Enter`||n.key===` `)&&(e.forEach(e=>e.classList.remove(`selected`)),t.classList.add(`selected`))})}),s()});