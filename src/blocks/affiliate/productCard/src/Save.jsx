import { useBlockProps } from '@wordpress/block-editor';

import { refreshAffiliateWidgetsOnSave } from '../../shared/helpers';
import ProductCard from './ProductCard';
import { AttributesType } from './propTypes';

const Save = ({ attributes }) => {
  refreshAffiliateWidgetsOnSave();
  return (
    // eslint-disable-next-line react/jsx-props-no-spreading
    <div {...useBlockProps.save()}>
      {attributes?.productCardSnippet && (
        <ProductCard
          productCardSnippet={attributes.productCardSnippet}
        />
      )}
    </div>
  );
};

Save.propTypes = {
  attributes: AttributesType.isRequired,
};

export default Save;
